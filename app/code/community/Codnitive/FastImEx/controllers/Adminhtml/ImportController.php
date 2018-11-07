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

class Codnitive_FastImEx_Adminhtml_ImportController extends Mage_Adminhtml_Controller_Action
{

    protected function _construct()
    {
        $this->setUsedModuleName('Codnitive_FastImEx');
    }
    
    protected function _isAllowed()
    {
        if (!Mage::getModel('fastimex/config')->isActive()) {
            $url = Mage::helper("adminhtml")->getUrl('adminhtml/system_config/edit', array('section' => 'codnitivedeveloper'));
            $this->_getSession()->addError($this->__('You must enable the extension. Click <a href="%s">here</a>', $url));
            return false;
        }
        return Mage::getSingleton('admin/session')->isAllowed('codall/fastimex/import');
    }
    
    public function indexAction()
    {
        if (!Mage::getModel('fastimex/config')->isActive()) {
            $this->_getSession()->addError($this->__('You must enable the extension.'));
            $this->_redirect('adminhtml/system_config/edit/section/codnitivedeveloper');
            return;
        }
        
        $maxUploadSize = Mage::helper('importexport')->getMaxUploadSize();
        $this->_getSession()->addNotice(
            $this->__('Total size of uploadable files must not exceed %s', $maxUploadSize)
        );
        $this->_title($this->__('Fast Import Export'))->_title($this->__('Import'));
        $this->loadLayout();
        
        $this->_setActiveMenu('codall/fastimex/import');
        $this->_addBreadcrumb($this->__('Fast Import Export'), $this->__('Fast Import Export'));
        $this->_addBreadcrumb($this->__('Import'), $this->__('Import'));

        $this->renderLayout();
    }

    public function startAction()
    {
        if (!Mage::getModel('fastimex/config')->isActive()) {
            $this->_getSession()->addError($this->__('You must enable the extension.'));
            $this->_redirect('adminhtml/system_config/edit/section/codnitivedeveloper');
            return;
        }
        
        $data = $this->getRequest()->getPost();
        if ($data) {
            $importModel = Mage::getModel('fastimex/import');
            
            try {
                if ($data['file_source'] === 'upload') {
                    $fileName = $importModel->uploadFile($data);
                    $data['file_name'] = $fileName;
                }
                
                set_time_limit(0);
                $importModel->setImportData($data);
                if ($data['entity'] === 'catalog_product') {
                    $entities     = $importModel->getEntities();
                    $importApi    = Mage::getModel('fastimex/import_api');
                    $importResult = $importApi->importEntities(
                        $entities,
                        $data['entity'],
                        $data['behavior']
                    );
                }
                else {
                    $importModel->updateAttribute();
                }
                
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_redirect('*/*/index');
                return;
            }
            $this->_getSession()->addSuccess($this->__('Import successfully done.'));
            $this->_redirect('*/*/index');
            return;
        } else {
            $this->_getSession()->addError($this->__('No data to import.'));
            $this->_redirect('*/*/index');
        }
    }

}