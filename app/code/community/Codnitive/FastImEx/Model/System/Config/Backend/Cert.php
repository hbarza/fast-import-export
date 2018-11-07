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

class Codnitive_FastImEx_Model_System_Config_Backend_Cert extends Mage_Core_Model_Config_Data
{
    
    protected function _beforeSave()
    {
        $value = $this->getValue();
        $fieldConf = $this->getFieldConfig();
        
        if ($value) {
            try {
                $nameSpace = Codnitive_FastImEx_Model_Config::getNamespace();

                $sernum = Mage::getStoreConfig($nameSpace . 'sernum');
                $regcod = Mage::getStoreConfig($nameSpace . 'regcod');
                $ownnam = Mage::getStoreConfig($nameSpace . 'ownnam');
                $ownmai = Mage::getStoreConfig($nameSpace . 'ownmai');

                $condition = empty($sernum) || !$sernum || empty($regcod) || !$regcod
                    || empty($ownnam) || !$ownnam || empty($ownmai) || !$ownmai;

                if ($condition) {
                    $err = Mage::helper('fastimex')->__('%s extension not registered.', 
                            Mage::helper('fastimex')->__(Codnitive_FastImEx_Model_Config::EXTENSION_NAME));
                    Mage::throwException($err);
                }
            }
            catch (Exception $e) {
                Mage::throwException($e->getMessage());
                return $this;
            }
        }

        return $this;
    }
}
