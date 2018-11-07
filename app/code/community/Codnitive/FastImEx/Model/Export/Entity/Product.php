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

/**
 * Export entity product model
 *
 * @category   Codnitive
 * @package    Codnitive_FastImEx
 * @author     Hassan Barza <support@codnitive.com>
 */
class Codnitive_FastImEx_Model_Export_Entity_Product extends Mage_ImportExport_Model_Export_Entity_Product
{
    
    public function export()
    {
        if (!Mage::getModel('fastimex/config')->isActive()) {
            return parent::export();
        }
        
        set_time_limit(0);
        $validAttrCodes  = $this->_getExportAttrCodes();
        $writer          = $this->getWriter();
        $defaultStoreId  = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

        $memoryLimit = trim(ini_get('memory_limit'));
        $lastMemoryLimitLetter = strtolower($memoryLimit[strlen($memoryLimit)-1]);
        switch($lastMemoryLimitLetter) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
                break;
            default:
                $memoryLimit = 250000000;
        }

        $memoryPerProduct = 100000;
        $memoryUsagePercent = 0.8;
        $minProductsLimit = 500;

        $limitProducts = intval(($memoryLimit  * $memoryUsagePercent - memory_get_usage(true)) / $memoryPerProduct);
        if ($limitProducts < $minProductsLimit) {
            $limitProducts = $minProductsLimit;
        }
        $offsetProducts = 0;
        $exportLimit = $this->_parameters['export_filter']['querylimit'];

        while (true) {
            ++$offsetProducts;

            $dataRows        = array();
            $rowCategories   = array();
            $rowWebsites     = array();
            $rowTierPrices   = array();
            $rowGroupPrices  = array();
            $rowMultiselects = array();
            $mediaGalery     = array();

            foreach ($this->_storeIdToCode as $storeId => &$storeCode) {
                $limitCounter = 0;
                $collection = $this->_prepareEntityCollection(Mage::getResourceModel('catalog/product_collection'));
                $collection
                    ->setStoreId($storeId)
                    ->setPage($offsetProducts, $limitProducts);
                if ($collection->getCurPage() < $offsetProducts) {
                    break;
                }
                $collection->load();

                if ($collection->count() == 0) {
                    break;
                }

                if ($defaultStoreId == $storeId) {
                    $collection->addCategoryIds()->addWebsiteNamesToResult();

                    $rowTierPrices = $this->_prepareTierPrices($collection->getAllIds());
                    $rowGroupPrices = $this->_prepareGroupPrices($collection->getAllIds());

                    $mediaGalery = $this->_prepareMediaGallery($collection->getAllIds());
                }
                foreach ($collection as $itemId => $item) {
                    ++$limitCounter;
                    if ((int)$exportLimit && $limitCounter > $exportLimit) {
                        break;
                    }
                    $rowIsEmpty = true;

                    foreach ($validAttrCodes as &$attrCode) {
                        $attrValue = $item->getData($attrCode);

                        if (!empty($this->_attributeValues[$attrCode])) {
                            if ($this->_attributeTypes[$attrCode] == 'multiselect') {
                                $attrValue = explode(',', $attrValue);
                                $attrValue = array_intersect_key(
                                    $this->_attributeValues[$attrCode],
                                    array_flip($attrValue)
                                );
                                $rowMultiselects[$itemId][$attrCode] = $attrValue;
                            } else if (isset($this->_attributeValues[$attrCode][$attrValue])) {
                                $attrValue = $this->_attributeValues[$attrCode][$attrValue];
                            } else {
                                $attrValue = null;
                            }
                        }
                        if ($storeId != $defaultStoreId
                            && isset($dataRows[$itemId][$defaultStoreId][$attrCode])
                            && $dataRows[$itemId][$defaultStoreId][$attrCode] == $attrValue
                        ) {
                            $attrValue = null;
                        }
                        if (is_scalar($attrValue)) {
                            $dataRows[$itemId][$storeId][$attrCode] = $attrValue;
                            $rowIsEmpty = false;
                        }
                    }
                    if ($rowIsEmpty) {
                        unset($dataRows[$itemId][$storeId]);
                    } else {
                        $attrSetId = $item->getAttributeSetId();
                        $dataRows[$itemId][$storeId][self::COL_STORE]    = $storeCode;
                        $dataRows[$itemId][$storeId][self::COL_ATTR_SET] = $this->_attrSetIdToName[$attrSetId];
                        $dataRows[$itemId][$storeId][self::COL_TYPE]     = $item->getTypeId();

                        if ($defaultStoreId == $storeId) {
                            $rowWebsites[$itemId]   = $item->getWebsites();
                            $rowCategories[$itemId] = $item->getCategoryIds();
                        }
                    }
                    $item = null;
                }
                $collection->clear();
            }

            if ($collection->getCurPage() < $offsetProducts) {
                break;
            }

            $allCategoriesIds = array_merge(array_keys($this->_categories), array_keys($this->_rootCategories));
            foreach ($rowCategories as &$categories) {
                $categories = array_intersect($categories, $allCategoriesIds);
            }

            $productIds = array_keys($dataRows);
            $stockItemRows = $this->_prepareCatalogInventory($productIds);

            $linksRows = $this->_prepareLinks($productIds);
            $linkIdColPrefix = array(
                Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED   => '_links_related_',
                Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL    => '_links_upsell_',
                Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL => '_links_crosssell_',
                Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED   => '_associated_'
            );
            $configurableProductsCollection = Mage::getResourceModel('catalog/product_collection');
            $configurableProductsCollection->addAttributeToFilter(
                'entity_id',
                array(
                    'in'    => $productIds
                )
            )->addAttributeToFilter(
                'type_id',
                array(
                    'eq'    => Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE
                )
            );
            $configurableData = array();
            while ($product = $configurableProductsCollection->fetchItem()) {
                $productAttributesOptions = $product->getTypeInstance(true)->getConfigurableOptions($product);

                foreach ($productAttributesOptions as $productAttributeOption) {
                    $configurableData[$product->getId()] = array();
                    foreach ($productAttributeOption as $optionValues) {
                        $priceType = $optionValues['pricing_is_percent'] ? '%' : '';
                        $configurableData[$product->getId()][] = array(
                            '_super_products_sku'           => $optionValues['sku'],
                            '_super_attribute_code'         => $optionValues['attribute_code'],
                            '_super_attribute_option'       => $optionValues['option_title'],
                            '_super_attribute_price_corr'   => $optionValues['pricing_value'] . $priceType
                        );
                    }
                }
            }

            $customOptionsData    = array();
            $customOptionsDataPre = array();
            $customOptCols        = array(
                '_custom_option_store', '_custom_option_type', '_custom_option_title', '_custom_option_is_required',
                '_custom_option_price', '_custom_option_sku', '_custom_option_max_characters',
                '_custom_option_sort_order', '_custom_option_row_title', '_custom_option_row_price',
                '_custom_option_row_sku', '_custom_option_row_sort'
            );

            foreach ($this->_storeIdToCode as $storeId => &$storeCode) {
                $options = Mage::getResourceModel('catalog/product_option_collection')
                    ->reset()
                    ->addTitleToResult($storeId)
                    ->addPriceToResult($storeId)
                    ->addProductToFilter($productIds)
                    ->addValuesToResult($storeId);

                foreach ($options as $option) {
                    $row = array();
                    $productId = $option['product_id'];
                    $optionId  = $option['option_id'];
                    $customOptions = isset($customOptionsDataPre[$productId][$optionId])
                                   ? $customOptionsDataPre[$productId][$optionId]
                                   : array();

                    if ($defaultStoreId == $storeId) {
                        $row['_custom_option_type']           = $option['type'];
                        $row['_custom_option_title']          = $option['title'];
                        $row['_custom_option_is_required']    = $option['is_require'];
                        $row['_custom_option_price'] = $option['price']
                            . ($option['price_type'] == 'percent' ? '%' : '');
                        $row['_custom_option_sku']            = $option['sku'];
                        $row['_custom_option_max_characters'] = $option['max_characters'];
                        $row['_custom_option_sort_order']     = $option['sort_order'];

                        $defaultTitles[$option['option_id']] = $option['title'];
                    } elseif ($option['title'] != $customOptions[0]['_custom_option_title']) {
                        $row['_custom_option_title'] = $option['title'];
                    }
                    $values = $option->getValues();
                    if ($values) {
                        $firstValue = array_shift($values);
                        $priceType  = $firstValue['price_type'] == 'percent' ? '%' : '';

                        if ($defaultStoreId == $storeId) {
                            $row['_custom_option_row_title'] = $firstValue['title'];
                            $row['_custom_option_row_price'] = $firstValue['price'] . $priceType;
                            $row['_custom_option_row_sku']   = $firstValue['sku'];
                            $row['_custom_option_row_sort']  = $firstValue['sort_order'];

                            $defaultValueTitles[$firstValue['option_type_id']] = $firstValue['title'];
                        } elseif ($firstValue['title'] != $customOptions[0]['_custom_option_row_title']) {
                            $row['_custom_option_row_title'] = $firstValue['title'];
                        }
                    }
                    if ($row) {
                        if ($defaultStoreId != $storeId) {
                            $row['_custom_option_store'] = $this->_storeIdToCode[$storeId];
                        }
                        $customOptionsDataPre[$productId][$optionId][] = $row;
                    }
                    foreach ($values as $value) {
                        $row = array();
                        $valuePriceType = $value['price_type'] == 'percent' ? '%' : '';

                        if ($defaultStoreId == $storeId) {
                            $row['_custom_option_row_title'] = $value['title'];
                            $row['_custom_option_row_price'] = $value['price'] . $valuePriceType;
                            $row['_custom_option_row_sku']   = $value['sku'];
                            $row['_custom_option_row_sort']  = $value['sort_order'];
                        } elseif ($value['title'] != $customOptions[0]['_custom_option_row_title']) {
                            $row['_custom_option_row_title'] = $value['title'];
                        }
                        if ($row) {
                            if ($defaultStoreId != $storeId) {
                                $row['_custom_option_store'] = $this->_storeIdToCode[$storeId];
                            }
                            $customOptionsDataPre[$option['product_id']][$option['option_id']][] = $row;
                        }
                    }
                    $option = null;
                }
                $options = null;
            }
            foreach ($customOptionsDataPre as $productId => &$optionsData) {
                $customOptionsData[$productId] = array();

                foreach ($optionsData as $optionId => &$optionRows) {
                    $customOptionsData[$productId] = array_merge($customOptionsData[$productId], $optionRows);
                }
                unset($optionRows, $optionsData);
            }
            unset($customOptionsDataPre);

            if ($offsetProducts == 1) {
                $headerCols = array_merge(
                    array(
                        self::COL_SKU, self::COL_STORE, self::COL_ATTR_SET,
                        self::COL_TYPE, self::COL_CATEGORY, self::COL_ROOT_CATEGORY, '_product_websites'
                    ),
                    $validAttrCodes,
                    reset($stockItemRows) ? array_keys(end($stockItemRows)) : array(),
                    array(),
                    array(
                        '_links_related_sku', '_links_related_position', '_links_crosssell_sku',
                        '_links_crosssell_position', '_links_upsell_sku', '_links_upsell_position',
                        '_associated_sku', '_associated_default_qty', '_associated_position'
                    ),
                    array('_tier_price_website', '_tier_price_customer_group', '_tier_price_qty', '_tier_price_price'),
                    array('_group_price_website', '_group_price_customer_group', '_group_price_price'),
                    array(
                        '_media_attribute_id',
                        '_media_image',
                        '_media_lable',
                        '_media_position',
                        '_media_is_disabled'
                    )
                );

                if ($customOptionsData) {
                    $headerCols = array_merge($headerCols, $customOptCols);
                }

                if ($configurableData) {
                    $headerCols = array_merge($headerCols, array(
                        '_super_products_sku', '_super_attribute_code',
                        '_super_attribute_option', '_super_attribute_price_corr'
                    ));
                }

                $writer->setHeaderCols($headerCols);
            }

            foreach ($dataRows as $productId => &$productData) {
                foreach ($productData as $storeId => &$dataRow) {
                    if ($defaultStoreId != $storeId) {
                        $dataRow[self::COL_SKU]      = null;
                        $dataRow[self::COL_ATTR_SET] = null;
                        $dataRow[self::COL_TYPE]     = null;
                    } else {
                        $dataRow[self::COL_STORE] = null;
                        $dataRow += $stockItemRows[$productId];
                    }

                    $this->_updateDataWithCategoryColumns($dataRow, $rowCategories, $productId);
                    if ($rowWebsites[$productId]) {
                        $dataRow['_product_websites'] = $this->_websiteIdToCode[array_shift($rowWebsites[$productId])];
                    }
                    if (!empty($rowTierPrices[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($rowTierPrices[$productId]));
                    }
                    if (!empty($rowGroupPrices[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($rowGroupPrices[$productId]));
                    }
                    if (!empty($mediaGalery[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($mediaGalery[$productId]));
                    }
                    foreach ($linkIdColPrefix as $linkId => &$colPrefix) {
                        if (!empty($linksRows[$productId][$linkId])) {
                            $linkData = array_shift($linksRows[$productId][$linkId]);
                            $dataRow[$colPrefix . 'position'] = $linkData['position'];
                            $dataRow[$colPrefix . 'sku'] = $linkData['sku'];

                            if (null !== $linkData['default_qty']) {
                                $dataRow[$colPrefix . 'default_qty'] = $linkData['default_qty'];
                            }
                        }
                    }
                    if (!empty($customOptionsData[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($customOptionsData[$productId]));
                    }
                    if (!empty($configurableData[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($configurableData[$productId]));
                    }
                    if(!empty($rowMultiselects[$productId])) {
                        foreach ($rowMultiselects[$productId] as $attrKey => $attrVal) {
                            if (!empty($rowMultiselects[$productId][$attrKey])) {
                                $dataRow[$attrKey] = array_shift($rowMultiselects[$productId][$attrKey]);
                            }
                        }
                    }

                    $writer->writeRow($dataRow);
                }
                $largestLinks = 0;

                if (isset($linksRows[$productId])) {
                    $linksRowsKeys = array_keys($linksRows[$productId]);
                    foreach ($linksRowsKeys as $linksRowsKey) {
                        $largestLinks = max($largestLinks, count($linksRows[$productId][$linksRowsKey]));
                    }
                }
                $additionalRowsCount = max(
                    count($rowCategories[$productId]),
                    count($rowWebsites[$productId]),
                    $largestLinks
                );
                if (!empty($rowTierPrices[$productId])) {
                    $additionalRowsCount = max($additionalRowsCount, count($rowTierPrices[$productId]));
                }
                if (!empty($rowGroupPrices[$productId])) {
                    $additionalRowsCount = max($additionalRowsCount, count($rowGroupPrices[$productId]));
                }
                if (!empty($mediaGalery[$productId])) {
                    $additionalRowsCount = max($additionalRowsCount, count($mediaGalery[$productId]));
                }
                if (!empty($customOptionsData[$productId])) {
                    $additionalRowsCount = max($additionalRowsCount, count($customOptionsData[$productId]));
                }
                if (!empty($configurableData[$productId])) {
                    $additionalRowsCount = max($additionalRowsCount, count($configurableData[$productId]));
                }
                if (!empty($rowMultiselects[$productId])) {
                    foreach($rowMultiselects[$productId] as $attributes) {
                        $additionalRowsCount = max($additionalRowsCount, count($attributes));
                    }
                }

                if ($additionalRowsCount) {
                    for ($i = 0; $i < $additionalRowsCount; $i++) {
                        $dataRow = array();

                        $this->_updateDataWithCategoryColumns($dataRow, $rowCategories, $productId);
                        if ($rowWebsites[$productId]) {
                            $dataRow['_product_websites'] = $this
                                ->_websiteIdToCode[array_shift($rowWebsites[$productId])];
                        }
                        if (!empty($rowTierPrices[$productId])) {
                            $dataRow = array_merge($dataRow, array_shift($rowTierPrices[$productId]));
                        }
                        if (!empty($rowGroupPrices[$productId])) {
                            $dataRow = array_merge($dataRow, array_shift($rowGroupPrices[$productId]));
                        }
                        if (!empty($mediaGalery[$productId])) {
                            $dataRow = array_merge($dataRow, array_shift($mediaGalery[$productId]));
                        }
                        foreach ($linkIdColPrefix as $linkId => &$colPrefix) {
                            if (!empty($linksRows[$productId][$linkId])) {
                                $linkData = array_shift($linksRows[$productId][$linkId]);
                                $dataRow[$colPrefix . 'position'] = $linkData['position'];
                                $dataRow[$colPrefix . 'sku'] = $linkData['sku'];

                                if (null !== $linkData['default_qty']) {
                                    $dataRow[$colPrefix . 'default_qty'] = $linkData['default_qty'];
                                }
                            }
                        }
                        if (!empty($customOptionsData[$productId])) {
                            $dataRow = array_merge($dataRow, array_shift($customOptionsData[$productId]));
                        }
                        if (!empty($configurableData[$productId])) {
                            $dataRow = array_merge($dataRow, array_shift($configurableData[$productId]));
                        }
                        if(!empty($rowMultiselects[$productId])) {
                            foreach($rowMultiselects[$productId] as $attrKey=>$attrVal) {
                                if(!empty($rowMultiselects[$productId][$attrKey])) {
                                    $dataRow[$attrKey] = array_shift($rowMultiselects[$productId][$attrKey]);
                                }
                            }
                        }
                        $writer->writeRow($dataRow);
                    }
                }
            }
        }
        return $writer->getContents();
    }

    protected function _getExportAttrCodes()
    {
        if (null === self::$attrCodes) {
            if (!empty($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP])
                    && is_array($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP])) {
                $skipAttr = array_flip($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP]);
            } else {
                $skipAttr = array();
            }
            $attrCodes = array();

            foreach ($this->filterAttributeCollection($this->getAttributeCollection()) as $attribute) {
                $condition = (!isset($skipAttr[$attribute->getAttributeId()])
                        || in_array($attribute->getAttributeCode(), $this->_permanentAttributes))
                        && 'multiline' !== $attribute->getFrontendInput();
                if ($condition) {
                    $attrCodes[] = $attribute->getAttributeCode();
                }
            }
            self::$attrCodes = $attrCodes;
        }
        return self::$attrCodes;
    }

}
