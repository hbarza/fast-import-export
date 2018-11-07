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

class Codnitive_FastImEx_Model_Import_Entity_Product extends Mage_ImportExport_Model_Import_Entity_Product
{

    protected $_eventPrefix = 'fastimex_entity_product';

    public function __construct()
    {
        $entityType = Mage::getSingleton('eav/config')->getEntityType($this->getEntityTypeCode());
        $this->_entityTypeId    = $entityType->getEntityTypeId();
        $this->_dataSourceModel = Codnitive_FastImEx_Model_Import::getDataSourceModel();
        $this->_connection      = Mage::getSingleton('core/resource')->getConnection('write');

        $this->_importAttributes()
             ->_initWebsites()
             ->_initStores()
             ->_initAttributeSets()
             ->_initTypeModels()
             ->_initCategories()
             ->_initSkus()
             ->_initCustomerGroups()
             ->_initOldData();
    }

    protected function _initOldData()
    {
        if ($this->_dataSourceModel->getBehavior() == Codnitive_FastImEx_Model_Import::BEHAVIOR_STOCK) {
            $entities = $this->_dataSourceModel->getEntities();
            foreach ($entities as $id => $entity) {
                if (isset($entity[self::COL_SKU]) && isset($this->_oldSku[$entity[self::COL_SKU]])) {
                    $entities[$id] = $entity + array(
                        self::COL_TYPE     => $this->_oldSku[$entity[self::COL_SKU]]['type_id'],
                        self::COL_ATTR_SET => $this->_attrSetIdToName[$this->_oldSku[$entity[self::COL_SKU]]['attr_set_id']]
                    );
                }
            }
            $this->_dataSourceModel->setEntities($entities);
        }
        return $this;
    }

    public function getCategories()
    {
        return $this->_categories;
    }

    public function getStores()
    {
        return $this->_storeCodeToId;
    }

    public function setUploader(Mage_ImportExport_Model_Import_Uploader $uploader)
    {
        $this->_fileUploader = $uploader;
        return $this;
    }

    protected function _importAttributes()
    {
        $productAttributes = Mage::getModel('eav/entity_type')->loadByCode($this->getEntityTypeCode())
            ->getAttributeCollection()
            ->addFieldToFilter('frontend_input', array('select', 'multiselect'))
            ->addFieldToFilter('is_user_defined', true);

        foreach ($productAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $sourceOptions = $attribute->getSource()->getAllOptions(false);

            $options = array();
            foreach ($this->_dataSourceModel->getEntities() as $rowData) {
                if (isset($rowData[$attributeCode]) && strlen(trim($rowData[$attributeCode]))) {
                    $optionExists = false;
                    foreach ($sourceOptions as $sourceOption) {
                        if ($rowData[$attributeCode] == $sourceOption['label']) {
                            $optionExists = true;
                            break;
                        }
                    }
                    if (!$optionExists) {
                        $options['value']['option_' . $rowData[$attributeCode]][0] = $rowData[$attributeCode];
                    }
                }
            }
            if (!empty($options)) {
                $attribute->setOption($options)->save();
            }
        }
        $this->_dataSourceModel->getIterator()->rewind();

        return $this;
    }

    public function importData()
    {
        return $this->_importData();
    }

    public function _importData()
    {
        Mage::dispatchEvent($this->_eventPrefix . '_before_import', array(
            'entity_model'      => $this,
            'data_source_model' => $this->_dataSourceModel,
            'uploader'          => $this->_getUploader()
        ));

        parent::_importData();

        Mage::dispatchEvent($this->_eventPrefix . '_after_import', array(
            'entity_model' => $this,
            'entities'     => $this->_newSku
        ));
        return $this->_newSku;
    }

    protected function _saveProducts()
    {
        $priceIsGlobal  = Mage::helper('catalog')->isPriceGlobal();
        $productLimit   = null;
        $productsQty    = null;
        
        $multilineCheck = Mage::helper('core')->isModuleEnabled('Codnitive_MultilineAttr')
            && Mage::getModel('multilineattr/config')->isActive();
        $conn           = Mage::helper('fastimex')->getDbConnection();
        $tableName      = Mage::getResourceModel('multilineattr/multiline')->getTable('multilineattr/multiline');

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = array();
            $entityRowsUp = array();
            $attributes   = array();
            $websites     = array();
            $categories   = array();
            $tierPrices   = array();
            $groupPrices  = array();
            $mediaGallery = array();
            $uploadedGalleryFiles = array();
            $previousType = null;
            $previousAttributeSet = null;

            foreach ($bunch as $rowNum => $rowData) {
                if ($multilineCheck) {
                    $this->_saveMultilineAttributes($rowData, $conn, $tableName);
                }
                
                $this->_filterRowData($rowData);
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                $rowScope = $this->getRowScope($rowData);

                if (self::SCOPE_DEFAULT == $rowScope) {
                    $rowSku = $rowData[self::COL_SKU];

                    if (isset($this->_oldSku[$rowSku])) {
                        $entityRowsUp[] = array(
                            'updated_at' => now(),
                            'entity_id'  => $this->_oldSku[$rowSku]['entity_id']
                        );
                    } else {
                        if (!$productLimit || $productsQty < $productLimit) {
                            $entityRowsIn[$rowSku] = array(
                                'entity_type_id'   => $this->_entityTypeId,
                                'attribute_set_id' => $this->_newSku[$rowSku]['attr_set_id'],
                                'type_id'          => $this->_newSku[$rowSku]['type_id'],
                                'sku'              => $rowSku,
                                'created_at'       => now(),
                                'updated_at'       => now()
                            );
                            $productsQty++;
                        } else {
                            $rowSku = null;
                            $this->_rowsToSkip[$rowNum] = true;
                            continue;
                        }
                    }
                } elseif (null === $rowSku) {
                    $this->_rowsToSkip[$rowNum] = true;
                    continue;
                } elseif (self::SCOPE_STORE == $rowScope) {
                    $rowData[self::COL_TYPE]     = $this->_newSku[$rowSku]['type_id'];
                    $rowData['attribute_set_id'] = $this->_newSku[$rowSku]['attr_set_id'];
                    $rowData[self::COL_ATTR_SET] = $this->_newSku[$rowSku]['attr_set_code'];
                }
                if (!empty($rowData['_product_websites'])) {
                    $websites[$rowSku][$this->_websiteCodeToId[$rowData['_product_websites']]] = true;
                }

                $categoryPath = empty($rowData[self::COL_CATEGORY]) ? '' : $rowData[self::COL_CATEGORY];
                if (!empty($rowData[self::COL_ROOT_CATEGORY])) {
                    $categoryId = $this->_categoriesWithRoots[$rowData[self::COL_ROOT_CATEGORY]][$categoryPath];
                    $categories[$rowSku][$categoryId] = true;
                } elseif (!empty($categoryPath)) {
                    $categories[$rowSku][$this->_categories[$categoryPath]] = true;
                }

                if (!empty($rowData['_tier_price_website'])) {
                    $tierPrices[$rowSku][] = array(
                        'all_groups'        => $rowData['_tier_price_customer_group'] == self::VALUE_ALL,
                        'customer_group_id' => ($rowData['_tier_price_customer_group'] == self::VALUE_ALL)
                            ? 0 : $rowData['_tier_price_customer_group'],
                        'qty'               => $rowData['_tier_price_qty'],
                        'value'             => $rowData['_tier_price_price'],
                        'website_id'        => (self::VALUE_ALL == $rowData['_tier_price_website'] || $priceIsGlobal)
                            ? 0 : $this->_websiteCodeToId[$rowData['_tier_price_website']]
                    );
                }
                if (!empty($rowData['_group_price_website'])) {
                    $groupPrices[$rowSku][] = array(
                        'all_groups'        => $rowData['_group_price_customer_group'] == self::VALUE_ALL,
                        'customer_group_id' => ($rowData['_group_price_customer_group'] == self::VALUE_ALL)
                            ? 0 : $rowData['_group_price_customer_group'],
                        'value'             => $rowData['_group_price_price'],
                        'website_id'        => (self::VALUE_ALL == $rowData['_group_price_website'] || $priceIsGlobal)
                            ? 0 : $this->_websiteCodeToId[$rowData['_group_price_website']]
                    );
                }
                foreach ($this->_imagesArrayKeys as $imageCol) {
                    if (!empty($rowData[$imageCol])) {
                        if (!array_key_exists($rowData[$imageCol], $uploadedGalleryFiles)) {
                            $uploadedGalleryFiles[$rowData[$imageCol]] = $this->_uploadMediaFiles($rowData[$imageCol]);
                        }
                        $rowData[$imageCol] = $uploadedGalleryFiles[$rowData[$imageCol]];
                    }
                }
                if (!empty($rowData['_media_image'])) {
                    $mediaGallery[$rowSku][] = array(
                        'attribute_id'      => $rowData['_media_attribute_id'],
                        'label'             => $rowData['_media_lable'],
                        'position'          => $rowData['_media_position'],
                        'disabled'          => $rowData['_media_is_disabled'],
                        'value'             => $rowData['_media_image']
                    );
                }
                $rowStore     = self::SCOPE_STORE == $rowScope ? $this->_storeCodeToId[$rowData[self::COL_STORE]] : 0;
                $productType  = isset($rowData[self::COL_TYPE]) ? $rowData[self::COL_TYPE] : null;
                if (!is_null($productType)) {
                    $previousType = $productType;
                }
                if (!is_null($rowData[self::COL_ATTR_SET])) {
                    $previousAttributeSet = $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET];
                }
                if (self::SCOPE_NULL == $rowScope) {
                    if (!is_null($previousAttributeSet)) {
                         $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET] = $previousAttributeSet;
                    }
                    if (is_null($productType) && !is_null($previousType)) {
                        $productType = $previousType;
                    }
                    if (is_null($productType)) {
                        continue;
                    }
                }
                $rowData = $this->_productTypeModels[$productType]->prepareAttributesForSave(
                    $rowData,
                    !isset($this->_oldSku[$rowSku])
                );
                try {
                    $attributes = $this->_prepareAttributes($rowData, $rowScope, $attributes, $rowSku, $rowStore);
                } catch (Exception $e) {
                    Mage::logException($e);
                    continue;
                }
            }

            $this->_saveProductEntity($entityRowsIn, $entityRowsUp)
                ->_saveProductWebsites($websites)
                ->_saveProductCategories($categories)
                ->_saveProductTierPrices($tierPrices)
                ->_saveProductGroupPrices($groupPrices)
                ->_saveMediaGallery($mediaGallery)
                ->_saveProductAttributes($attributes);
        }
        Mage::helper('fastimex')->closeDbConnection($conn);
        return $this;
    }
    
    protected function _saveMultilineAttributes($rowData, $conn, $tableName)
    {
        $entityId     = Mage::getModel('catalog/product')->getIdBySku($rowData[self::COL_SKU]);
        foreach($rowData as $code => $value) {
            if (preg_match('/_text_base$/', $code)) {
                $attributeId  = Mage::getModel('eav/entity_attribute')
                    ->loadByCode($this->_entityTypeId, str_replace('_text_base', '', $code))->getId();
                $query = "DELETE FROM $tableName 
                            WHERE entity_id = $entityId 
                                AND attribute_id = $attributeId 
                                AND entity_type_id = ". $this->_entityTypeId .";";
                @mysqli_query($conn, $query);
                
                $valueArray = Mage::helper('fastimex')->multiexplode(array(',', '<br />', '<br/>', '<br>'), $value);
                foreach ($valueArray as $val) {
                    $query = "INSERT INTO $tableName (entity_id, value, attribute_id, entity_type_id)
                                VALUES ('$entityId', '$val', '$attributeId', '". $this->_entityTypeId ."');";
                    @mysqli_query($conn, $query);
                }
            }
        }
        
        return $this;
    }
    
    protected function _getStockItemData()
    {
        $productIds = array_map(function($e) { return $e['entity_id']; }, $this->_newSku);
        $stockItemCollection = Mage::getModel('cataloginventory/stock_item')
            ->getCollection()
            ->addFieldToFilter('product_id', array('in' => $productIds));

        $stockItemData = array();
        foreach ($stockItemCollection as $stockItem) {
            $stockItemData[$stockItem['product_id']] = $stockItem;
        }
        return $stockItemData;
    }

    protected function _saveStockItem()
    {
        $defaultStockData = array(
            'manage_stock'                  => 1,
            'use_config_manage_stock'       => 1,
            'qty'                           => 0,
            'min_qty'                       => 0,
            'use_config_min_qty'            => 1,
            'min_sale_qty'                  => 1,
            'use_config_min_sale_qty'       => 1,
            'max_sale_qty'                  => 10000,
            'use_config_max_sale_qty'       => 1,
            'is_qty_decimal'                => 0,
            'backorders'                    => 0,
            'use_config_backorders'         => 1,
            'notify_stock_qty'              => 1,
            'use_config_notify_stock_qty'   => 1,
            'enable_qty_increments'         => 0,
            'use_config_enable_qty_inc'     => 1,
            'qty_increments'                => 0,
            'use_config_qty_increments'     => 1,
            'is_in_stock'                   => 0,
            'low_stock_date'                => null,
            'stock_status_changed_auto'     => 0
        );

        $entityTable = Mage::getResourceModel('cataloginventory/stock_item')->getMainTable();
        $helper      = Mage::helper('catalogInventory');

        if ($this->_connection->tableColumnExists($entityTable, 'is_decimal_divided')) {
            $defaultStockData['is_decimal_divided'] = 0;
        }

        $stockItemData = $this->_getStockItemData();

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $stockData = array();

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                if (self::SCOPE_DEFAULT != $this->getRowScope($rowData)) {
                    continue;
                }

                $row = array();
                $row['product_id'] = $this->_newSku[$rowData[self::COL_SKU]]['entity_id'];
                $row['stock_id'] = 1;

                if (isset($stockItemData[$row['product_id']])) {
                    $stockItem = $stockItemData[$row['product_id']];
                } else {
                    $stockItem = Mage::getModel('cataloginventory/stock_item');
                }
                $existStockData = $stockItem->getData();

                $row = array_merge(
                    $defaultStockData,
                    array_intersect_key($existStockData, $defaultStockData),
                    array_intersect_key($rowData, $defaultStockData),
                    $row
                );

                $stockItem->setData($row);

                if ($helper->isQty($this->_newSku[$rowData[self::COL_SKU]]['type_id'])) {
                    if ($stockItem->verifyNotification()) {
                        $stockItem->setLowStockDate(Mage::app()->getLocale()
                            ->date(null, null, null, false)
                            ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                        );
                    }
                    $stockItem->setStockStatusChangedAutomatically((int) !$stockItem->verifyStock());
                } else {
                    $stockItem->setQty(0);
                }
                $stockData[] = $stockItem->unsetOldData()->getData();
            }

            if ($stockData) {
                $this->_connection->insertOnDuplicate($entityTable, $stockData);
            }
        }
        return $this;
    }

}
