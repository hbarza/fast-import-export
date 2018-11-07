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

class Codnitive_FastImEx_Model_Observer
{
    const CATALOG_PRODUCT_ATTRIBUTE = 'catalog_product_attribute';
    const CATALOG_PRODUCT_PRICE     = 'catalog_product_price';
    const CATALOG_URL               = 'catalog_url';
    const CATALOG_PRODUCT_FLAT      = 'catalog_product_flat';
    const CATALOG_CATEGORY_FLAT     = 'catalog_category_flat';
    const CATALOG_CATAGORY_PRODUCT  = 'catalog_category_product';
    const CATALOGSEARCH_FULLTEXT    = 'catalogsearch_fulltext';
    const CATALOGINVENTORY_STOCK    = 'cataloginventory_stock';
    const TAG_SUMMARY               = 'tag_summary';
    const TARGETRULE                = 'targetrule';

    protected function _indexStock(&$event)
    {
        if (!$this->_checkIndexEverything()) {
            return Mage::getResourceSingleton('cataloginventory/indexer_stock')->catalogProductMassAction($event);
        }
        
        $process = $this->_getProcess(self::CATALOGINVENTORY_STOCK);
        if ($process->getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
            $process->reindexEverything();
        }
    }

    protected function _indexPrice(&$event)
    {
        if (!$this->_checkIndexEverything()) {
            return Mage::getResourceSingleton('catalog/product_indexer_price')->catalogProductMassAction($event);
        }
        
        $process = $this->_getProcess(self::CATALOG_PRODUCT_PRICE);
        if ($process->getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
            $process->reindexEverything();
        }
    }
    
    protected function _indexCategoryProduct(&$event)
    {
        if (!$this->_checkIndexEverything()) {
            return Mage::getResourceSingleton('catalog/category_indexer_product')->catalogProductMassAction($event);
        }
        
        $process = $this->_getProcess(self::CATALOG_CATAGORY_PRODUCT);
        if ($process->getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
            $process->reindexEverything();
        }
    }

    protected function _indexEav(&$event)
    {
        if (!$this->_checkIndexEverything()) {
            return Mage::getResourceSingleton('catalog/product_indexer_eav')->catalogProductMassAction($event);
        }
        
        $process = $this->_getProcess(self::CATALOG_PRODUCT_ATTRIBUTE);
        if ($process->getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
            $process->reindexEverything();
        }
    }

    protected function _indexSearch(&$productIds)
    {
        if (!$this->_checkIndexEverything()) {
            return Mage::getResourceSingleton('catalogsearch/fulltext')->rebuildIndex(null, $productIds);
        }
        
        $process = $this->_getProcess(self::CATALOGSEARCH_FULLTEXT);
        if ($process->getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
            $process->reindexEverything();
        }
    }
    
    protected function _indexProductFlat()
    {
        $process = $this->_getProcess(self::CATALOG_PRODUCT_FLAT);
        if ($process->getStatus() == Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX) {
            $process->reindexEverything();
        }
    }
    
    protected function _checkIndexEverything()
    {
        $postData  = $this->_getPostData();
        return ( $postData['custom_settings'] && $postData['index_everything']) 
            || (!$postData['custom_settings'] && Mage::getModel('fastimex/config')->indexEverything());
    }

    protected function _getIndexEvent(&$entityIds)
    {
        $event = Mage::getModel('index/event');
        $event->setNewData(array(
            'reindex_price_product_ids' => &$entityIds,
            'reindex_stock_product_ids' => &$entityIds,
            'product_ids'               => &$entityIds,
            'reindex_eav_product_ids'   => &$entityIds
        ));
        return $event;
    }

    protected function _isImageToImport($entity, $attribute)
    {
        return (isset($entity[$attribute . '_content']) && !empty($entity[$attribute . '_content'])
            && isset($entity[$attribute]) && !empty($entity[$attribute])
            && is_string($entity[$attribute]) && is_string($entity[$attribute . '_content']));
    }
    
    protected function _getPostData()
    {
        return Mage::app()->getRequest()->getPost();
    }
    
    protected function _getProcess($processCode)
    {
        return $this->_getIndexer()->getProcessByCode($processCode);
    }
    
    protected function _getIndexer()
    {
        return Mage::getSingleton('index/indexer');
    }

    public function indexProducts($observer)
    {
        $entityIds = array();
        foreach ($observer->getEntities() as $entity) {
            $entityIds[] = $entity['entity_id'];
        }
        if (!count($entityIds)) {
            return false;
        }

        $postData = $this->_getPostData();
        $event = $this->_getIndexEvent($entityIds);
        try {
            $condition = ( $postData['custom_settings'] && $postData['stock_index']) 
                      || (!$postData['custom_settings'] && Mage::getModel('fastimex/config')->getStockIndex());
            if ($condition) {
                $this->_indexStock($event);
            }
            $condition = ( $postData['custom_settings'] && $postData['price_index']) 
                      || (!$postData['custom_settings'] && Mage::getModel('fastimex/config')->getPriceIndex());
            if ($condition) {
                $this->_indexPrice($event);
            }
            $condition = ( $postData['custom_settings'] && $postData['category_product_index']) 
                      || (!$postData['custom_settings'] && Mage::getModel('fastimex/config')->getCategoryProductIndex());
            if ($condition) {
                $this->_indexCategoryProduct($event);
            }
            $condition = ( $postData['custom_settings'] && $postData['attribute_index']) 
                      || (!$postData['custom_settings'] && Mage::getModel('fastimex/config')->getAttributeIndex());
            if ($condition) {
                $this->_indexEav($event);
            }
            $condition = ( $postData['custom_settings'] && $postData['search_index']) 
                      || (!$postData['custom_settings'] && Mage::getModel('fastimex/config')->getSearchIndex());
            if ($condition) {
                $this->_indexSearch($entityIds);
            }
            $condition = ( $postData['custom_settings'] && $postData['product_index']) 
                      || (!$postData['custom_settings'] && Mage::getModel('fastimex/config')->getProductFlatIndex())
                      && Mage::getStoreConfigFlag('catalog/frontend/flat_catalog_product');
            if ($condition) {
                $this->_indexProductFlat();
            }
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
        return true;
    }

    protected function _createMediaImportFolder()
    {
        $ioAdapter = new Varien_Io_File();
        $ioAdapter->checkAndCreateFolder(Mage::getConfig()->getOptions()->getMediaDir() . '/import');
        return $this;
    }

    public function importMedia($observer)
    {
        $this->_createMediaImportFolder();

        $ioAdapter        = new Varien_Io_File();
        $entities         = $observer->getDataSourceModel()->getEntities();
        $uploader         = $observer->getUploader();
        $tmpImportFolder  = $uploader->getTmpDir();
        $attributes       = Mage::getResourceModel('catalog/product_attribute_collection')->getItems();
        $mediaAttr        = array();
        $mediaAttributeId = Mage::getModel('eav/entity_attribute')
            ->load('media_gallery', 'attribute_code')
            ->getAttributeId();

        foreach ($attributes as $attr) {
            if ($attr->getFrontendInput() === 'media_image') {
                $mediaAttr[] = $attr->getAttributeCode();
            }
        }

        foreach($entities as $key => $entity) {
            foreach ($mediaAttr as $attr) {
                if ($this->_isImageToImport($entity, $attr)) {
                    try {
                        $ioAdapter->open(array('path' => $tmpImportFolder));
                        $ioAdapter->write(end(explode('/', $entity[$attr])), base64_decode($entity[$attr . '_content']), 0666);

                        $entities[$key]['_media_attribute_id'] = $mediaAttributeId;
                        unset($entities[$key][$attr . '_content']);
                    } catch (Exception $e) {
                        Mage::throwException($e->getMessage());
                    }
                }
            }
        }
        $observer->getDataSourceModel()->setEntities($entities);

        return true;
    }
}
