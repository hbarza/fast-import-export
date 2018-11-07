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

class Codnitive_FastImEx_Model_System_Config_Backend_Name extends Mage_Core_Model_Config_Data
{
    
    protected function _beforeSave()
    {
        try{
            $value = (string) $this->getValue();
            $fieldConf = $this->getFieldConfig();
            
            if (empty($value)) {
                Mage::throwException(Mage::helper('fastimex')->__('%s is required.', 
                        Mage::helper('fastimex')->__($fieldConf->label)));
            }
            
            $validator = new Zend_Validate_Alpha(array('allowWhiteSpace' => true));
            if (!$validator->isValid($value)) {
                Mage::throwException(Mage::helper('fastimex')->__('%s is not valid.', 
                        Mage::helper('fastimex')->__($fieldConf->label)));
            }
        }
        catch (Exception $e) {
            Mage::throwException($e->getMessage());
            return $this;
        }
        
        return $this;
    }

}
