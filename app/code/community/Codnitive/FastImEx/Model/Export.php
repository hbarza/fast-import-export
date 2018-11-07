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
 * Export model
 *
 * @category   Codnitive
 * @package    Codnitive_FastImEx
 * @author     Hassan Barza <support@codnitive.com>
 */
class Codnitive_FastImEX_Model_Export extends Mage_ImportExport_Model_Export
{
    const FAST_PRICE_EXPORT_TEMPLATE     = 'codnitive/fastimex/export/price.phtml';
    const FAST_QTY_EXPORT_TEMPLATE       = 'codnitive/fastimex/export/qty.phtml';
    const FAST_ATTRIBUTE_EXPORT_TEMPLATE = 'codnitive/fastimex/export/attribute.phtml';
    const CSV_TMP_FILE = 'tmp.csv';
    
    public function getEntityAttributeCollection()
    {
        if (!Mage::getModel('fastimex/config')->isActive()) {
            return parent::getEntityAttributeCollection();
        }
        
        $collection = $this->_getEntityAdapter()->getAttributeCollection()
            ->addFieldToFilter('frontend_input', array('nlike' => 'multiline'));
        $item = Mage::getResourceModel('catalog/eav_attribute');
        
        $data = Array();
        $data['attribute_code'] = 'querylimit';
        $data['backend_type'] = 'text';
        $data['frontend_label'] = 'Query Limit';
        $item->setData($data);
        
        $collection->addItem($item);
        return $collection;
    }

    public function getFileName()
    {
        $fileName = $this->_data['file_name'];
        if (isset($fileName) && !empty($fileName)) {
            return $fileName . '.' . $this->_getWriter()->getFileExtension();
        }
        
        return parent::getFileName();
    }
    
    public function getTemplate($type)
    {
        switch($type) {
            case 'catalog_product_price':
                return self::FAST_PRICE_EXPORT_TEMPLATE;
                break;
            case 'catalog_product_qty':
                return self::FAST_QTY_EXPORT_TEMPLATE;
                break;
            case 'catalog_product_attr':
                return self::FAST_ATTRIBUTE_EXPORT_TEMPLATE;
                break;
        }
    }

    public function export()
    {
        $condition = $this->_data['entity'] === 'catalog_product'
            || $this->_data['entity'] === 'catalog_product_attr';
        if ($condition) {
            if ($this->_data['entity'] === 'catalog_product_attr') {
                $filters = $this->_data['export_filter'];
                $attributeCode = $filters['attribute_code'];
                $attributeValue = $filters['attribute_value'];
                if (empty($attributeCode)) {
                    Mage::throwException('Attribute Code is required.');
                    return;
                }
                if (empty($attributeValue)) {
                    Mage::throwException('Attribute Value is required.');
                    return;
                }
                $this->_data['entity'] = 'catalog_product';
                $this->_data['export_filter'][$attributeCode] = $attributeValue;
            }
            return parent::export();
        }
        
        if (isset($this->_data[self::FILTER_ELEMENT_GROUP])) {
            $this->addLogComment(Mage::helper('importexport')->__('Begin export of %s', $this->getEntity()));
            $result = $this->_fetchResult();
            $countRows = substr_count(trim($result), "\n");
            if (!$countRows) {
                Mage::throwException(
                    Mage::helper('importexport')->__('There is no data for export')
                );
            }
            if ($result) {
                $this->addLogComment(array(
                    Mage::helper('importexport')->__('Exported %s rows.', $countRows),
                    Mage::helper('importexport')->__('Export has been done.')
                ));
            }
            return $result;
        } else {
            Mage::throwException(
                Mage::helper('importexport')->__('No filter data provided')
            );
        }
    }
    
    protected function _fetchResult()
    {
        $helper   = Mage::helper('fastimex');
        $filePath = Mage::getBaseDir('var') . DS .  self::CSV_TMP_FILE;
        $filePath = str_replace("\\", '/', $filePath);
        
        $filters = $this->_data['export_filter'];
        $tableCols  = 'p.entity_id';
        $colsHeader = "'entity_id'";
        if ($filters['sku']) {
            $tableCols .= ',p.sku';
            $colsHeader .= ",'sku'";
        }
        if ($filters['name']) {
            $tableCols .= ',n.value';
            $colsHeader .= ",'name'";
        }

        $query = $this->_getQuery($tableCols, $colsHeader, $filePath);
        $conn  = $helper->getDbConnection();
        
        set_time_limit(0);
        
        $result = @mysqli_query($conn, $query);
        if (!$result) {
            return '';
        }
        
        if (Mage::getModel('fastimex/config')->isRemoteDb()) {
            $this->_createFile($result, $filePath);
        }
        $helper->closeDbConnection($conn);
        $content = @file_get_contents($filePath);
        @unlink($filePath);
        return $content;
    }
    
    protected function _createFile($result, $filePath)
    {
        $io = new Varien_Io_File();
        $path = Mage::getBaseDir('var');
        $io->setAllowCreateFolders(true);
        $io->open(array('path' => $path));
        $io->streamOpen($filePath, 'w+');
        $io->streamLock(true);
        
        while ($row = @mysqli_fetch_row($result)) {
            $io->streamWriteCsv($row);
        }
        $result->free();
    }
    
    protected function _getQuery($tableCols, $colsHeader, $filePath)
    {
        $helper   = Mage::helper('fastimex');
        $entityTypeId = $helper->getProductEntityTypeId();
        
        $filters = $this->_data['export_filter'];
        $join  = '';
        $fieldName = '';
        $where = 'WHERE';
        $and = '';
        $extraCol = '';
        $extraHeader = '';
        $limit = '';
        $useNotNull = false;
        switch ($this->_data['entity']) {
            case 'catalog_product_price':
                $joinTable  = 'catalog_product_entity_decimal';
                $fieldName  = 'entity_id';
                $extraCol = ', v.value';
                $extraHeader = ", 'price'";
                $attributeId = $helper->getAttributeId('price');
                $and = " AND v.entity_type_id = $entityTypeId AND v.attribute_id = $attributeId ";
                $from = $filters['price']['from'];
                $to   = $filters['price']['to'];
                if ($from || '0' === $from) {
                    $where .= ' v.value >= ' . $from . ' AND';
                }
                if ($to || '0' === $to) {
                    $where .= ' v.value <= ' . $to . ' AND';
                }
                $useNotNull = true;
                break;
            case 'catalog_product_qty':
                $joinTable  = 'cataloginventory_stock_item';
                $fieldName  = 'product_id';
                $extraCol = ', v.qty';
                $extraHeader = ", 'qty'";
                $from = $filters['qty']['from'];
                $to   = $filters['qty']['to'];
                if ($from || '0' === $from) {
                    $where .= ' v.qty >= ' . $from . ' AND';
                }
                if ($to || '0' === $to) {
                    $where .= ' v.qty <= ' . $to . ' AND';
                }
                $useNotNull = true;
                break;
        }
        
        if ($filters['id_limit']['from']) {
            $where .= ' p.entity_id > ' . $filters['id_limit']['from'] . ' AND';
        }
        if ($filters['id_limit']['to']) {
            $where .= ' p.entity_id < ' . $filters['id_limit']['to'] . ' AND';
        }
        if ($filters['limit']) {
            $limit = ' LIMIT ' . $filters ['limit'];
        }
                
        $join = " LEFT JOIN `$joinTable` AS `v` ON `p`.`entity_id` = `v`.`$fieldName` $and ";
        if ($filters['name']) {
            $join .= " LEFT JOIN `catalog_product_entity_varchar` AS `n` ON `p`.`entity_id` = `n`.`entity_id` ";
                $attributeId = $helper->getAttributeId('name');
                $where .= " n.entity_type_id = $entityTypeId AND n.attribute_id = $attributeId AND";
        }
        if ($useNotNull) {
            $where .= " v.$fieldName IS NOT NULL ";
        }
        $where = rtrim($where, 'AND');
        $query = <<< QRY
            SELECT $colsHeader $extraHeader
            UNION ALL
            SELECT $tableCols $extraCol FROM `catalog_product_entity` AS `p` 
                $join 
            $where 
            GROUP BY p.entity_id
            $limit
QRY;

        if (!Mage::getModel('fastimex/config')->isRemoteDb()) {
            $query .= <<< QRY
            
            INTO OUTFILE '$filePath'
                FIELDS TERMINATED BY ','
                ENCLOSED BY '"'
                LINES TERMINATED BY '\n'
QRY;
        }
        $query .= ";";
        return $query;
    }
    
}
