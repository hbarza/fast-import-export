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
 * Import Api
 *
 * @category   Codnitive
 * @package    Codnitive_FastImEx
 * @author     Hassan Barza <support@codnitive.com>
 */

class Codnitive_FastImEx_Model_Import_Api extends Mage_Api_Model_Resource_Abstract
{

    protected $_api;

    public function __construct()
    {
        $this->_api = Mage::getModel('fastimex/import');
        Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);
    }

    public function importEntities($entities, $entityType = null, $behavior = null)
    {
        $this->_setEntityTypeCode($entityType ? $entityType : Mage_Catalog_Model_Product::ENTITY);
        $this->_setBehavior($behavior ? $behavior : Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        $this->_api->getDataSourceModel()->setEntities($entities);
        try {
            $result = $this->_api->importSource();
            $errorsCount = $this->_api->getErrorsCount();
            if ($errorsCount > 0) {
                Mage::throwException("There were {$errorsCount} errors during the import process. " .
                    "Please be aware that valid entities were still imported.");
            };
        } catch(Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_fault('import_failed', $e->getMessage());
        }

        return array($result);
    }

    protected function _setEntityTypeCode($entityType)
    {
        try {
            $this->_api->getDataSourceModel()->setEntityTypeCode($entityType);
        } catch(Mage_Core_Exception $e) {
            $this->_fault('invalid_entity_type', $e->getMessage());
        }
    }

    protected function _setBehavior($behavior)
    {
        try {
            $this->_api->getDataSourceModel()->setBehavior($behavior);
        } catch(Mage_Core_Exception $e) {
            $this->_fault('invalid_behavior', $e->getMessage());
        }
    }
}
