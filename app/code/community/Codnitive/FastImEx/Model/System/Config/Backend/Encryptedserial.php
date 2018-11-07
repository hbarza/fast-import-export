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

class Codnitive_FastImEx_Model_System_Config_Backend_Encryptedserial extends Mage_Core_Model_Config_Data
{
    
    protected function _afterLoad()
    {
        $value = (string) $this->getValue();
        if (!empty($value) && ($decrypted = Mage::helper('core')->decrypt($value))) {
            $this->setValue($decrypted);
        }
    }
    
    protected function _beforeSave()
    {
        $value = (string) $this->getValue();
        $fieldConf = $this->getFieldConfig();
            
        if (empty($value)) {
            Mage::throwException(Mage::helper('fastimex')->__('%s is required.', 
                    Mage::helper('fastimex')->__($fieldConf->label)));
        }
        
        if (preg_match('/^\*+$/', $this->getValue())) {
            $value = $this->getOldValue();
        }
        
        $pattern = '/^[a-zA-Z0-9]{32}\:[A-Z]{2}\d{4}[A-Z]{3}\d{4}$/';
        $validator = new Zend_Validate_Regex(array('pattern' => $pattern));
        if (!$validator->isValid($value)) {
            Mage::throwException(Mage::helper('fastimex')->__('%s is not valid.', 
                    Mage::helper('fastimex')->__($fieldConf->label)));
        }
        
        if (!empty($value) && ($encrypted = Mage::helper('core')->encrypt($value))) {
            $this->setValue($encrypted);
        }
    }
    
    public function getOldValue()
    {
        return Mage::helper('core')->decrypt(parent::getOldValue());
    }

}
