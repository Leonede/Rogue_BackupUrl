<?php
class Rogue_BackupUrl_Model_Attribute_Backend_Url extends Mage_Catalog_Model_Attribute_Backend_Urlkey_Abstract
{
    /**
     * Format url key attribute before save
     *
     * @param Varien_Object $object
     * @return Mage_Catalog_Model_Category_Attribute_Backend_Urlkey
     */
    public function beforeSave($object)
    {
        $attributeName = $this->getAttribute()->getName();

        $urlKey = $object->getData($attributeName);
        if ($urlKey == false) {
            return $this;
        }

        if (!$this->isValidUrl($urlKey)) {
            Mage::throwException(Mage::helper('cms')->__("URL attribute '%s' contains capital letters or disallowed symbols.", $attributeName));
        }

        $object->setData($attributeName, $urlKey);

        return $this;
    }


    public function isValidUrl($url)
    {
        return preg_match('/^[a-z0-9][a-z0-9_\/-]+(\.[a-z0-9_-]+)?$/', $url);
    }


}