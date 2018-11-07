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

class Codnitive_FastImEx_Helper_Imex extends Mage_Core_Helper_Data
{
    protected $_exportType = array(
        'catalog_product_price' => 'Price',
        'catalog_product_qty'   => 'Quantity',
        'catalog_product_attr'  => 'Quick Filter',
        'catalog_product'       => 'Advanced'
    );
    
    protected $_importType = array(
        'catalog_product_price' => 'Price',
        'catalog_product_qty'   => 'Quantity',
        'catalog_product'       => 'Full Data'
    );
    
    protected $_fileType = array(
        'csv'   => 'CSV',
        'xml'   => 'XML',
        'xlsx'  => 'Excel Workbook'
    );
    
    protected $_fileSourceType = array(
        'server' => 'Read From Server',
        'upload' => 'Upload File'
    );
    
    protected function getSelectOptions($data, $addSelect = true)
    {
        $html = $addSelect ? '<option selected="selected" value="">' . $this->__('-- Please Select --') . '</option>' . PHP_EOL : '';
        foreach($data as $val => $option) {
            $html .= '<option value="' . $val . '">' . $this->__($option) . '</option>' . PHP_EOL;
        }
        
        return $html;
    }
    
    public function getExportTypeOptions($addSelect = true)
    {
        return $this->getSelectOptions($this->_exportType, $addSelect);
    }
    
    public function getImportTypeOptions($addSelect = false)
    {
        return $this->getSelectOptions($this->_importType, $addSelect);
    }
    
    public function getFileTypeOptions($addSelect = false)
    {
        return $this->getSelectOptions($this->_fileType, $addSelect);
    }
    
    public function getFileSourceOptions($addSelect = false)
    {
        return $this->getSelectOptions($this->_fileSourceType, $addSelect);
    }
    
    public function getBehaviorOptions()
    {
        $html = '';
        $behaviors = Mage::getModel('importexport/source_import_behavior')->toOptionArray();
        foreach($behaviors as $option) {
            $selected = $option['value'] === Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE 
                ? $selected = ' selected="selected" '
                : '';
            $html .= '<option value="' . $option['value'] . '"' . $selected . '>' . $this->__($option['label']) . '</option>' . PHP_EOL;
        }
        return $html;
    }
    
    public function getAllowdFileExtensions()
    {
        return array_keys($this->_fileType);
    }
    
    public function getYesNoOption($data)
    {
        $options = Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray();

        return Mage::app()->getLayout()->createBlock('core/html_select')
                    ->setName($data['name'])
                    ->setId($data['id'])
                    ->setTitle($data['title'])
                    ->setClass($data['class'])
                    ->setExtraParams($data['extra'])
                    ->setValue($data['value'])
                    ->setOptions($options)
                    ->getHtml();
    }
    
    public function getImportButtonHtml()
    {
        return '&nbsp;&nbsp;<button onclick="editForm.submit();" class="scalable save"'
            . ' type="button"><span><span><span>' . $this->__('Import') . '</span></span></span></button>';
    }

    public function getImportStartUrl()
    {
        return Mage::getUrl('*/*/start');
    }

}
