<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 14.12.14
 * Time: 14:35
 */ 
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$setup = new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');

$installer->startSetup();

$setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'backup_url', array(
    'group'                    => 'General',
    'type'                     => 'varchar',
    'input'                    => 'text',
    'frontend_class'           => 'validate-identifier',
    'backend'                  => 'rgburl/attribute_backend_url',
    'label'                    => 'Backup URL',
    'global'                   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'                  => 1,
    'required'                 => 0,
    'visible_on_front'         => 0,
    'is_html_allowed_on_front' => 0,
    'is_configurable'          => 0,
    'source'                   => '',
    'searchable'               => 0,
    'filterable'               => 0,
    'comparable'               => 0,
    'unique'                   => false,
    'user_defined'             => false,
    'is_user_defined'          => false,
    'used_in_product_listing'  => false
));

$setup->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'cat_backup_url', array(
    'group'                    => 'General',
    'type'                     => 'varchar',
    'input'                    => 'text',
    'frontend_class'           => 'validate-identifier',
    'backend'                  => 'rgburl/attribute_backend_url',
    'label'                    => 'Backup URL',
    'global'                   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'                  => 1,
    'required'                 => 0,
    'visible_on_front'         => 0,
    'is_html_allowed_on_front' => 0,
    'is_configurable'          => 0,
    'source'                   => '',
    'searchable'               => 0,
    'filterable'               => 0,
    'comparable'               => 0,
    'unique'                   => false,
    'user_defined'             => false,
    'is_user_defined'          => false,
    'used_in_product_listing'  => false
));

$installer->endSetup();