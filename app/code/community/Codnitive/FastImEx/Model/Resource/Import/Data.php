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

class Codnitive_FastImEx_Model_Resource_Import_Data implements IteratorAggregate
{

    protected $_entities = array();
    protected $_bunches = array();
    protected $_entityTypeCode = null;
    protected $_behavior = null;
    protected $_iterator = null;

    public function getIterator()
    {
        if ($this->_iterator === null) {
            $this->_generateBunches();
            if (empty($this->_bunches)) {
                Mage::throwException('Import resource model was not provided any entities.');
            }
            $this->_iterator = new ArrayIterator($this->_bunches);
        }
        return $this->_iterator;
    }

    public function _generateBunches()
    {
        $this->_bunches = array();
        $products = array();
        $bunchNum = Mage::getModel('fastimex/config')->getImportBunchNum();
        $i = 1;
        foreach ($this->_entities as $product) {
            $products[$i] = $product;
            if (($i && $i % $bunchNum == 0) || $i == count($this->_entities)) {
                $this->_bunches[] = $products;
                $products = array();
            }
            $i++;
        }
    }

    public function setEntities($entities)
    {
        if (count($entities)) {
            $this->_entities = $entities;
            $this->_iterator = null;
        }
        return $this;
    }

    public function getEntities()
    {
        return $this->_entities;
    }

    public function getEntityTypeCode()
    {
        if ($this->_entityTypeCode === null) {
            Mage::throwException('Import resource model was not provided any entity type.');
        }
        return $this->_entityTypeCode;
    }

    public function getBehavior()
    {
        if ($this->_behavior === null) {
            Mage::throwException('Import resource model was not provided any import behavior.');
        }
        return $this->_behavior;
    }

    public function setBehavior($behavior)
    {
        $allowedBehaviors = array(
            Mage_ImportExport_Model_Import::BEHAVIOR_APPEND,
            Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE,
            Mage_ImportExport_Model_Import::BEHAVIOR_DELETE
        );
        if (!in_array($behavior, $allowedBehaviors)) {
            Mage::throwException('Specified import behavior (%s) is not in allowed behaviors: %s', $behavior, implode(', ', $allowedBehaviors));
        }
        $this->_behavior = $behavior;
        return $this;
    }

    public function setEntityTypeCode($entityTypeCode)
    {
        $allowedEntities = array_keys(Mage_ImportExport_Model_Config::getModels(Codnitive_FastImEx_Model_Import::CONFIG_KEY_ENTITIES));
        if (!in_array($entityTypeCode, $allowedEntities)) {
            Mage::throwException('Specified entity type (%s) is not in allowed entity types: %s', $entityTypeCode, implode(', ', $allowedEntities));
        }
        $this->_entityTypeCode = $entityTypeCode;
        return $this;
    }

    public function getNextBunch()
    {
        if ($this->_iterator === null) {
            $this->_iterator = $this->getIterator();
            $this->_iterator->rewind();
        }
        if ($this->_iterator->valid()) {
            $dataRow = $this->_iterator->current();
            $this->_iterator->next();
        } else {
            $this->_iterator = null;
            $dataRow = null;
        }
        return $dataRow;
    }

}
