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
 * Import model
 *
 * @category   Codnitive
 * @package    Codnitive_FastImEx
 * @author     Hassan Barza <support@codnitive.com>
 */

class Codnitive_FastImEx_Model_Import extends Mage_ImportExport_Model_Import
{
    
    const CONFIG_KEY_ENTITIES = 'global/fastimex/import_entities';
    const BEHAVIOR_STOCK = 'stock';
    const BEHAVIOR_DELETE_IF_NOT_EXIST = 'delete_if_not_exist';
    const LOG_DIRECTORY = 'log/fastimex/';
    protected $_debugMode = true;
    
    protected $_data = array();
    
    protected $_defaultProductAttributes = array(
        '_attribute_set'    => 'Default',
        '_product_websites' => 'base',
        'status'            => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
        'visibility'        => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
        'tax_class_id'      => 0,
        'is_in_stock'       => 1
    );

    public static function getDataSourceModel()
    {
        return Mage::getResourceSingleton('fastimex/import_data');
    }
    
    public function setImportData($data)
    {
        $this->_data = $data;
        return $this;
    }

    public function importSource()
    {
        $this->setData(
            array(
                'entity'   => self::getDataSourceModel()->getEntityTypeCode(),
                'behavior' => self::getDataSourceModel()->getBehavior()
            )
        );
        $this->addLogComment(Mage::helper('importexport')->__('Begin import of "%s" with "%s" behavior', $this->getEntity(), $this->getBehavior()));

        $result = $this->_getEntityAdapter()->importData();
        $this->addLogComment(
            array(
                Mage::helper('importexport')->__(
                    'Checked rows: %d, checked entities: %d, invalid rows: %d, total errors: %d',
                    $this->getProcessedRowsCount(), $this->getProcessedEntitiesCount(),
                    $this->getInvalidRowsCount(), $this->getErrorsCount()
                ),
                Mage::helper('importexport')->__('Import has been done successfuly.')
            )
        );

        foreach ($this->getErrors() as $errorCode => $rows) {
            $this->addLogComment($errorCode . ' ' . Mage::helper('importexport')->__('in rows') . ': ' . implode(', ', $rows));
        }
        return $result;
    }

    protected function _getEntityAdapter()
    {
        if (!$this->_entityAdapter) {
            $validTypes = Mage_ImportExport_Model_Config::getModels(self::CONFIG_KEY_ENTITIES);

            if (isset($validTypes[$this->getEntity()])) {
                try {
                    $this->_entityAdapter = Mage::getModel($validTypes[$this->getEntity()]['model']);
                } catch (Exception $e) {
                    Mage::logException($e);
                    Mage::throwException(
                        Mage::helper('importexport')->__('Invalid entity model')
                    );
                }
                if (!($this->_entityAdapter instanceof Mage_ImportExport_Model_Import_Entity_Abstract)) {
                    Mage::throwException(
                        Mage::helper('importexport')->__('Entity adapter object must be an instance of Mage_ImportExport_Model_Import_Entity_Abstract')
                    );
                }
            } else {
                Mage::throwException(Mage::helper('importexport')->__('Invalid entity'));
            }
            if ($this->getEntity() != $this->_entityAdapter->getEntityTypeCode()) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Input entity code is not equal to entity adapter code')
                );
            }
            $this->_entityAdapter->setParameters($this->getData());
        }
        return $this->_entityAdapter;
    }
    
    public function updateAttribute()
    {
        try {
            $queries = $this->_getQueries();
            $result  = array();
            $conn = Mage::helper('fastimex')->getDbConnection();
            if (Mage::getModel('fastimex/config')->isLoadDataInfileEnabled()) {
                foreach ($queries as $query) {
                    $result[] = @mysqli_query($conn, $query);
                }
            }
            else {
                $result = $this->updateTableRemotely($conn, $queries['base']);
            }
            Mage::helper('fastimex')->closeDbConnection($conn);
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
        return $result;
    }
    
    public function updateTableRemotely($conn, $baseQuery)
    {
        try {
            $path = $this->getDirectoryPath() . $this->_data['file_name'];
            $rows     = array_map('str_getcsv', file($path));
            array_shift($rows);
            $result  = array();
            $pattern = array('_id_', '_value_');
            foreach ($rows as $row) {
                $query = str_replace($pattern, array($row[0], end($row)), $baseQuery);
                $result[] = @mysqli_query($conn, $query);
            }
            return $result;
        }
        catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }
    
    protected function _getQueries()
    {
        $path = $this->getDirectoryPath() . $this->_data['file_name'];
        $path = str_replace("\\", "/", $path);
        if (!$this->checkFile($path)) {
            Mage::throwException('File not exists or not readable.');
        }
        
        $entityTypeId = Mage::helper('fastimex')->getProductEntityTypeId();
        $csvHeaders  = trim(str_replace('"', '', $this->getCsvHeaders($path)));
        $tableFields = $this->_getTableFields($csvHeaders);
        $entityId = $desId    = 'entity_id';
        $tmpValue = $desValue = 'value';
        $updateTable = $where = '';
        switch ($this->_data['entity']) {
            case 'catalog_product_price':
                $tmpValue = 'price';
                $updateTable  = 'catalog_product_entity_decimal';
                $attributeId = Mage::helper('fastimex')->getAttributeId('price');
                $where = " WHERE tbl.entity_type_id = $entityTypeId AND tbl.attribute_id = $attributeId";
                if (!Mage::getModel('fastimex/config')->isLoadDataInfileEnabled()) {
                    $where .= " AND tbl.$desId = _id_";
                }
                break;
                
            case 'catalog_product_qty':
                $tmpValue = 'qty';
                $desValue = 'qty';
                $desId    = 'product_id';
                $updateTable  = 'cataloginventory_stock_item';
                if (!Mage::getModel('fastimex/config')->isLoadDataInfileEnabled()) {
                    $where .= " WHERE tbl.$desId = _id_";
                }
                break;
        }
        $queries = array();
        if (Mage::getModel('fastimex/config')->isLoadDataInfileEnabled()) {
            $queries['creat'] = <<< QRYCRT
                CREATE TEMPORARY TABLE tmptbl($tableFields) CHARACTER SET utf8 COLLATE utf8_general_ci;
QRYCRT;
            $queries['load'] = <<< QRYLD
                LOAD DATA LOCAL INFILE '$path' 
                INTO TABLE tmptbl CHARACTER SET utf8
                FIELDS TERMINATED BY ',' 
                ENCLOSED BY '"' 
                LINES TERMINATED BY '\n'
                IGNORE 1 LINES
                ($csvHeaders);
QRYLD;
            $queries['update'] = <<< QRYUPDT
                UPDATE $updateTable AS tbl
                INNER JOIN tmptbl ON tmptbl.$entityId = tbl.$desId
                SET tbl.$desValue = tmptbl.$tmpValue
                $where;
QRYUPDT;
            $queries['drop'] = <<< QRYDRP
                DROP TEMPORARY TABLE tmptbl;
QRYDRP;
        }
        else {
            $queries['base'] = <<< QRYBS
                UPDATE $updateTable AS tbl
                SET tbl.$desValue = _value_ 
                $where;
QRYBS;
        }
        
        return $queries;
    }
    
    public function getEntities()
    {
        try {
            $path = $this->getDirectoryPath() . $this->_data['file_name'];
            if (!$this->checkFile($path)) {
                Mage::throwException('File not exists or not readable.');
            }
            
            $entities = array();
            if (($handle = @fopen($path, "r")) !== FALSE) {
                $first = 1;
                while (($row = @fgetcsv($handle, 0, ",", '"')) !== FALSE) {
                    if (1 === $first) {
                        $headers = $row;
                        $first = 0;
                    }
                    else {
                        $tmpArray = array_combine($headers, $row);
                        foreach ($this->_defaultProductAttributes as $key => $val) {
                            if ('' === $tmpArray[$key]) {
                                $tmpArray[$key] = $val;
                            }
                        }
                        $entities[] = $tmpArray;
                    }
                }
                @fclose($handle);
            }
            return $entities;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }
    
    public function getCsvHeaders($file)
    {
        $handle = @fopen($file, "r");
        if ($handle) {
            return @fgets($handle);
        }
        return null;
    }
    
    public function uploadFile()
    {
        if (isset($_FILES['import_file']['name']) && $_FILES['import_file']['name'] != '') {
            try {
                $uploader = new Varien_File_Uploader('import_file');
                $uploader->setAllowedExtensions(Mage::helper('fastimex/imex')->getAllowdFileExtensions());
                $uploader->setAllowRenameFiles(false);
                $uploader->setFilesDispersion(false);
                $path = $this->getDirectoryPath();
                $uploader->save($path, $_FILES['import_file']['name'] );             
                return $uploader->getUploadedFileName();
            } catch (Exception $e) {
                Mage::throwException($e->getMessage());
                return;
            }
        }
    }
    
    public function getDirectoryPath($createDirectory = true)
    {
        $path = Mage::getBaseDir('var') . DS . 'fastimex' . DS;
        if(!@is_dir($path) && $createDirectory){
            @mkdir($path, 0777, true);
        }
        
        return $path;
    }
    
    public function checkFile($path)
    {
        if(!@file_exists($path) || !@is_readable($path)) {
            return false;
        }
        return true;
    }
    
    protected function _getTableFields($csvHeaders)
    {
        $list = explode(',', $csvHeaders);
        $str = '';
        foreach ($list as $field) {
            $str .= $field . ' ' . $this->_getFeildType($field) . ',';
        }
        
        return rtrim($str, ',');
    }
    
    protected function _getFeildType($field)
    {
        switch ($field) {
            case 'price':
            case 'qty':
                $type = 'DECIMAL(12,4)';
                break;
                
            case 'sku':
                $type = 'VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci';
                break;
                
            case 'entity_id':
                $type = 'INT(10)';
                break;
                
            case 'name':
            default:
                $type = 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci';
        }
        return $type;
    }

    public function addLogComment($debugData)
    {
        if (is_array($debugData)) {
            $this->_logTrace = array_merge($this->_logTrace, $debugData);
        } else {
            $this->_logTrace[] = $debugData;
        }
        if (!$this->_debugMode) {
            return $this;
        }

        if (!$this->_logInstance) {
            if (!$this->getRunAt()) {
                $this->setRunAt(date('H-i-s'));
            }
            $dirName  = date('Y' . DS .'m' . DS .'d' . DS);
            $fileName = join('_', array(
                $this->getRunAt(),
                $this->getBehavior(),
                $this->getEntity()
            ));
            $dirPath = Mage::getBaseDir('var') . DS . self::LOG_DIRECTORY . $dirName;
            if (!@is_dir($dirPath)) {
                @mkdir($dirPath, 0777, true);
            }
            $fileName = substr(strstr(self::LOG_DIRECTORY, DS), 1) . $dirName . $fileName . '.log';
            $this->_logInstance = Mage::getModel('core/log_adapter', $fileName)
                ->setFilterDataKeys($this->_debugReplacePrivateDataKeys);
        }
        $this->_logInstance->log($debugData);
        return $this;
    }

}
