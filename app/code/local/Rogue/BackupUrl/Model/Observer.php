<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 14.12.14
 * Time: 15:12
 */


class Rogue_BackupUrl_Model_Observer
{

    /**
     * @param $observer
     */
    public function controllerPredispatch($observer)
    {
        /**
         * @var $action Mage_Core_Controller_Varien_Action
         */
        $action = $observer->getEvent()->getControllerAction();
        $request = $action->getRequest();
        if ( $request->getModuleName() != 'catalog' && $request->getActionName() != 'noRoute') {
            return;
        }

        $redirect_url = $this->_getRedirectUrl($request);
        if (!$redirect_url){
            return;
        }
        $action->getResponse()
            ->setRedirect(Mage::getUrl($redirect_url), 301) // Permanently
            ->sendResponse();
        exit();
    }

    /**
     * @param $request Mage_Core_Controller_Request_Http
     * @return bool|string
     */
    protected  function _getRedirectUrl($request)
    {
        $id = $request->getParam('id');

        if ($request->getControllerName() == 'product') {
            return Mage::getResourceModel('catalog/product')
                ->getAttributeRawValue($id, 'backup_url', Mage::app()->getStore());
        }

        return Mage::getResourceModel('catalog/category')
            ->getAttributeRawValue($id, 'cat_backup_url', Mage::app()->getStore());

    }

    public function productDeleteBefore($observer)
    {
        /**
         * @var $product Mage_Catalog_Model_Product
         */
        $product = $observer->getEvent()->getProduct();

        $backupUrl = $product->getBackupUrl();
        if (!$backupUrl){
            $backupUrl = Mage::getResourceModel('catalog/product')
                ->getAttributeRawValue($product->getId(), 'backup_url', $product->getStoreId());

            if (!$backupUrl){
                return;
            }
        }

        $urlCollection = Mage::getResourceModel('core/url_rewrite_collection')
            ->addFieldToFilter('product_id', $product->getId());

        $this->_createBackupRedirect($urlCollection, $backupUrl);
    }

    public function categoryDeleteBefore($observer)
    {
        /**
         * @var $product Mage_Catalog_Model_Category
         */
        $category = $observer->getEvent()->getCategory();

        $backupUrl = $category->getCatBackupUrl();
        if (!$backupUrl){
            $backupUrl = Mage::getResourceModel('catalog/category')
                ->getAttributeRawValue($category->getId(), 'cat_backup_url', $category->getStoreId());

            if (!$backupUrl){
                return;
            }
        }

        $urlCollection = Mage::getResourceModel('core/url_rewrite_collection')
            ->addFieldToFilter('category_id', $category->getId())
            ->addFieldToFilter('product_id', array('null' => true));

        $this->_createBackupRedirect($urlCollection, $backupUrl);
    }

    /**
     * @param $urlCollection Mage_Core_Model_Resource_Url_Rewrite_Collection
     * @param $backupUrl string
     */
    protected function _createBackupRedirect($urlCollection, $backupUrl)
    {
        $oldUrls = array();


        foreach($urlCollection as $url)
        {
            $storeId = $url->getStoreId();
            $url->delete();
            if (!in_array($url->getTargetPath(), $oldUrls[$storeId])){
                $oldUrls[$storeId][] = $url->getTargetPath();
                $this->_saveRewrite($url->getTargetPath(), $backupUrl, $storeId);
            }

            if (!in_array($url->getRequestPath(), $oldUrls[$storeId])){
                $oldUrls[$storeId][] = $url->getRequestPath();
                $this->_saveRewrite($url->getRequestPath(), $backupUrl, $storeId);
            }

        }
    }

    protected function _saveRewrite($request, $target, $storeId)
    {
        $rewrite = Mage::getModel('core/url_rewrite');
        $rewrite->setIdPath('backup_'.$this->_generateUniqueIdPath())
            ->setTargetPath($target)
            ->setRequestPath($request)
            ->setStoreId($storeId)
            ->setOptions('RP')
            ->setDescription('Backup URL');

        $rewrite->save();
    }

    /**
     * Return unique string based on the time in microseconds.
     *
     * @return string
     */
    protected function _generateUniqueIdPath()
    {
        return str_replace('0.', '', str_replace(' ', '_', microtime()));
    }
}