<?php
/**
 * CODNITIVE
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE_EULA.html.
 * It is also available through the world-wide-web at this URL:
 * http://www.codnitive.com/en/terms-of-service-softwares/
 * http://www.codnitive.com/fa/terms-of-service-softwares/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade to newer
 * versions in the future.
 *
 * @category   Codnitive
 * @package    Codnitive_FastImEx
 * @author     Hassan Barza <support@codnitive.com>
 * @copyright  Copyright (c) 2012 CODNITIVE Co. (http://www.codnitive.com)
 * @license    http://www.codnitive.com/en/terms-of-service-softwares/ End User License Agreement (EULA 1.0)
 */

class Codnitive_FastImEx_Model_Config
{
    
    const PATH_NAMESPACE      = 'codnitivedeveloper';
    const EXTENSION_NAMESPACE = 'fastimex';
    
    const EXTENSION_NAME    = 'Fast Import Export';
    const EXTENSION_VERSION = '1.0.78';
    const EXTENSION_EDITION = 'Basic';

    public static function getNamespace()
    {
        return self::PATH_NAMESPACE . '/' . self::EXTENSION_NAMESPACE . '/';
    }
    
    public function getExtensionName()
    {
        return self::EXTENSION_NAME;
    }
    
    public function getExtensionVersion()
    {
        return self::EXTENSION_VERSION;
    }
    
    public function getExtensionEdition()
    {
        return self::EXTENSION_EDITION;
    }

    public function isActive()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'active');
    }
    
    public function enableFileFormat()
    {
        return $this->isActive() && false;
    }
    
    public function isRemoteDb()
    {
        return $this->isActive() && Mage::getStoreConfigFlag(self::getNamespace() . 'remote_db');
    }
    
    public function isLoadDataInfileEnabled()
    {
        return $this->isRemoteDb() && Mage::getStoreConfigFlag(self::getNamespace() . 'load_data_infile');
    }
    
    public function indexEverything()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'index_everything');
    }
    
    public function getImportBunchNum()
    {
        return Mage::getStoreConfig(self::getNamespace() . 'bunch_num');
    }
    
    public function getStockIndex()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'enable_stock_index');
    }
    
    public function getPriceIndex()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'enable_price_index');
    }
    
    public function getCategoryProductIndex()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'enable_category_relation_index');
    }
    
    public function getAttributeIndex()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'enable_attribute_index');
    }
    
    public function getSearchIndex()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'enable_search_index');
    }
    
    public function getUrlRewriteIndex()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'enable_rewrite_index');
    }
    
    public function getProductFlatIndex()
    {
        return Mage::getStoreConfigFlag(self::getNamespace() . 'enable_product_index');
    }
    
}
