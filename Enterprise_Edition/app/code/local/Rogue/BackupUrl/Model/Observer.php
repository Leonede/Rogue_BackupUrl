<?php

/**
 * @category    Rogue
 * @package     Rogue_BackupUrl
 * @author      Leo Tamasheuski <leon.tom@tut.by>
 */
class Rogue_BackupUrl_Model_Observer
{
    /**
     * @var array
     */
    protected $_categoryProductUrlSet = array();

    /**
     * Redirecting a disabled product/category to a backup URL
     *
     * @param $observer
     */
    public function controllerPredispatch($observer)
    {
        /** @var $action Mage_Core_Controller_Varien_Action */
        $action = $observer->getEvent()->getControllerAction();
        $request = $action->getRequest();

        $redirect_url = $this->_getRedirectUrlByType($request->getParam('id'), $request->getControllerName());
        if (!$redirect_url){
            return;
        }
        if (strpos($redirect_url, Mage::getBaseUrl()) === false) {
            $redirect_url = Mage::getUrl($redirect_url);
        }
        $action->getResponse()
            ->setRedirect($redirect_url, 301) // 301-Permanently
            ->sendResponse();
        exit();
    }

    /**
     * @param      $id    int
     * @param      $type  string
     * @param null $store Mage_Core_Model_Store | int
     *
     * @return bool|string
     */
    protected  function _getRedirectUrlByType($id, $type, $store = null)
    {
        if (is_null($store)){
            $store = Mage::app()->getStore();
        }

        if ($type == 'product') {
            $url = Mage::getResourceModel('catalog/product')
                ->getAttributeRawValue($id, 'backup_url', $store);

            if (!$url && $store) {
                /** only for store oriented */
                if ($store instanceof Mage_Core_Model_Store) {
                    $store = $store->getId();
                }
                $url = $this->_getCategoryUrlByProductId($id, $store);
            }

            return $url;
        }

        return Mage::getResourceModel('catalog/category')
            ->getAttributeRawValue($id, 'cat_backup_url', $store);
    }

    /**
     * If Product don't have Backup URL, then get category url like Backup.
     *
     * @param int $productId
     * @param int $storeId
     *
     * @return string|null
     */
    protected function _getCategoryUrlByProductId($productId, $storeId)
    {
        if (!$storeId){
            return null;
        }

        if (!isset($this->_categoryProductUrlSet[$productId . '_' . $storeId])){
            $categoryCollection = Mage::getResourceModel('catalog/category_collection')
                ->joinField(
                    'product_id',
                    'catalog/category_product',
                    'product_id',
                    'category_id = entity_id',
                    null
                )
                ->addFieldToFilter('product_id', $productId)
                ->setStoreId($storeId)
                ->addIsActiveFilter()
                ->setOrder(array('level', 'position'))
                ->setPageSize(1);

            Mage::getSingleton('catalog/factory')->getCategoryUrlRewriteHelper()
                ->joinTableToEavCollection($categoryCollection, $storeId);

            $requestPath = Mage::getSingleton('core/resource')
                ->getConnection(Mage_Core_Model_Resource::DEFAULT_READ_RESOURCE)
                ->getIfNullSql('url_rewrite.request_path', 'default_ur.request_path');

            $categoryCollection->getSelect()->where($requestPath . ' IS NOT NULL');

            $category = $categoryCollection->getFirstItem();

            if ($category->getId()) {
                $this->_categoryProductUrlSet[$productId . '_' . $storeId] = $category->getRequestPath();
            }
        }

        return $this->_categoryProductUrlSet[$productId . '_' . $storeId];
    }

    /**
     * Create a redirect from Backup URL for deleted product
     *
     * @param $observer
     */
    public function productDeleteBefore($observer)
    {
        /** @var $product Mage_Catalog_Model_Product */
        $product = $observer->getEvent()->getProduct();

        if (!($backupUrl = $product->getBackupUrl())) {
            foreach ($product->getStoreIds() as $storeId) {
                if ($backupUrl = $this->_getRedirectUrlByType($product->getId(), 'product', $storeId)) {
                    break;
                }
            }
            if (!$backupUrl) {
                return;
            }
        }

        $resource = Mage::getSingleton('core/resource');
        $connect  = $resource->getConnection('core_read');
        $id       = $product->getId();

        $select = $connect->select()
            ->from(array('ur' => $resource->getTableName('enterprise_urlrewrite/url_rewrite')),
                new Zend_Db_Expr('ur.url_rewrite_id, ur.request_path, ur.target_path, ur.store_id')
            )
            ->joinLeft(
                array('rp' => $resource->getTableName('enterprise_catalog/product')),
                $connect->quoteInto(
                    'rp.url_rewrite_id = ur.url_rewrite_id AND ur.entity_type = ?',
                    Enterprise_Catalog_Model_Product::URL_REWRITE_ENTITY_TYPE),
                null
            )
            ->joinLeft(
                array('red' => $resource->getTableName('enterprise_urlrewrite/redirect')),
                $connect->quoteInto(
                    'red.redirect_id = ur.value_id AND ur.entity_type = ?',
                    Enterprise_UrlRewrite_Model_Redirect::URL_REWRITE_ENTITY_TYPE),
                'red.redirect_id'
            )
            ->columns(new Zend_Db_Expr('if (rp.product_id IS NOT NULL, rp.product_id, red.product_id) as product_id '))
            ->where('rp.product_id = ?', $id)
            ->orWhere('red.product_id = ?', $id);

        $urlSet = $connect->fetchAll($select);

        $connect->query( $select->deleteFromSelect(new Zend_Db_Expr('`ur`, `red`')) );

        $this->_createBackupRedirect($urlSet, $product);
    }

    /**
     * Create a redirect from Backup URL for deleted category
     *
     * @param $observer
     */
    public function categoryDeleteBefore($observer)
    {
        /** @var $category Mage_Catalog_Model_Category */
        $category = $observer->getEvent()->getCategory();

        if (!($backupUrl = $category->getCatBackupUr()) && !($backupUrl = $this->_getRedirectUrlByType($category->getId(), 'category', 0))) {
            return;
        }

        $resource = Mage::getSingleton('core/resource');
        $connect  = $resource->getConnection('core_read');
        $id       = $category->getId();

        $select = $connect->select()
            ->from(array('ur' => $resource->getTableName('enterprise_urlrewrite/url_rewrite')),
                new Zend_Db_Expr('ur.url_rewrite_id, ur.request_path, ur.target_path, ur.store_id')
            )
            ->joinLeft(
                array('rc' => $resource->getTableName('enterprise_catalog/category')),
                $connect->quoteInto(
                    'rc.url_rewrite_id = ur.url_rewrite_id AND ur.entity_type = ?',
                    Enterprise_Catalog_Model_Category::URL_REWRITE_ENTITY_TYPE),
                null
            )
            ->joinLeft(
                array('red' => $resource->getTableName('enterprise_urlrewrite/redirect')),
                $connect->quoteInto(
                    'red.redirect_id = ur.value_id AND red.product_id IS NULL AND ur.entity_type = ?',
                    Enterprise_UrlRewrite_Model_Redirect::URL_REWRITE_ENTITY_TYPE),
                'red.redirect_id'
            )
            ->columns(new Zend_Db_Expr('if (rc.category_id IS NOT NULL, rc.category_id, red.category_id) as category_id '))
            ->where('rc.category_id = ?', $id)
            ->orWhere('red.category_id = ?', $id);

        $urlSet = $connect->fetchAll($select);

        $connect->query( $select->deleteFromSelect(new Zend_Db_Expr('`ur`, `red`')) );

        $this->_createBackupRedirect($urlSet, $category);
    }

    /**
     * @param $urlSet array
     * @param $entity Mage_Catalog_Model_Category | Mage_Catalog_Model_Product
     */
    protected function _createBackupRedirect($urlSet, $entity)
    {
        $oldUrls = array();

        foreach($urlSet as $urlRow)
        {
            $entityType = $entity instanceof Mage_Catalog_Model_Product ? 'product': 'category';
            $storeId    = $urlRow['store_id'];
            $backupUrl  = $this->_getRedirectUrlByType(
                $entity->getId(),
                $entityType,
                $storeId
            );

            if ($entityType == 'product' && !$backupUrl && $storeId == 0){
                foreach($entity->getStoreIds() as $store) {
                    if ($catUrl = $this->_getCategoryUrlByProductId($entity->getId(), $store)){
                        if (!in_array($urlRow['target_path'], $oldUrls[$store])){
                            $oldUrls[$store][] = $urlRow['target_path'];
                            $this->_saveRewrite($urlRow['target_path'], $catUrl, $store);
                        }

                        if (!in_array($urlRow['request_path'], $oldUrls[$store])){
                            $oldUrls[$store][] = $urlRow['request_path'];
                            $this->_saveRewrite($urlRow['request_path'], $catUrl, $store);
                        }
                    }
                }
                continue;
            }

            if (!$backupUrl){
                continue;
            }

            if (!in_array($urlRow['target_path'], $oldUrls[$storeId])){
                $oldUrls[$storeId][] = $urlRow['target_path'];
                $this->_saveRewrite($urlRow['target_path'], $backupUrl, $storeId);
            }

            if (!in_array($urlRow['request_path'], $oldUrls[$storeId])){
                $oldUrls[$storeId][] = $urlRow['request_path'];
                $this->_saveRewrite($urlRow['request_path'], $backupUrl, $storeId);
            }
        }
    }


    protected function _saveRewrite($request, $target, $storeId)
    {
        if (empty($request)){
            return;
        }

        $rewrite = Mage::getModel('enterprise_urlrewrite/redirect');

        $rewrite
            ->setTargetPath($target)
            ->setRequestPath($request)
            ->setIdentifier($request)
            ->setStoreId($storeId)
            ->setOptions('RP')
            ->setDescription('Backup URL');
        try{
            $rewrite->save();
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
