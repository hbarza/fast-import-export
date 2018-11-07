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

class Codnitive_FastImEx_Adminhtml_ExportController extends Mage_Adminhtml_Controller_Action
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
        
        $this->_title($this->__('Fast Import Export'))->_title($this->__('Export'));
        $this->loadLayout();
        
        $this->_setActiveMenu('codall/fastimex/export');
        $this->_addBreadcrumb($this->__('Fast Import Export'), $this->__('Fast Import Export'));
        $this->_addBreadcrumb($this->__('Export'), $this->__('Export'));

        $this->renderLayout();
    }
    
    public function exportAction()
    {
        if (!Mage::getModel('fastimex/config')->isActive()) {
            $this->_getSession()->addError($this->__('You must enable the extension.'));
            $this->_redirect('adminhtml/system_config/edit/section/codnitivedeveloper');
            return;
        }
        
        if ($this->getRequest()->getPost(Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP)) {
            try {
                $model = Mage::getModel('fastimex/export');
                $model->setData($this->getRequest()->getParams());

                return $this->_prepareDownloadResponse(
                    $model->getFileName(),
                    $model->export(),
                    $model->getContentType()
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($this->__('No valid data sent'));
            }
        } else {
            $this->_getSession()->addError($this->__('No valid data sent'));
        }
        return $this->_redirect('*/*/index');
    }

    public function getFilterAction()
    {
        if (!Mage::getModel('fastimex/config')->isActive()) {
            $this->_getSession()->addError($this->__('You must enable the extension.'));
            $this->_redirect('adminhtml/system_config/edit/section/codnitivedeveloper');
            return;
        }
        
        $data = $this->getRequest()->getParams();
        if ($this->getRequest()->isXmlHttpRequest() && $data) {
            try {
                $this->loadLayout();
                $export = Mage::getModel('fastimex/export');
                
                if ($data['entity'] === 'catalog_product') {
                    $attrFilterBlock = $this->getLayout()->getBlock('export.filter');

                    $export->filterAttributeCollection(
                        $attrFilterBlock->prepareCollection(
                            $export->setData($data)->getEntityAttributeCollection()
                        )
                    );
                    return $this->renderLayout();
                }
                else {
                    $data = $this->getRequest()->getParams();
                    $myBlock = $this->getLayout()->createBlock('adminhtml/template');
                    $myBlock->setTemplate($export->getTemplate($data['entity']));
                    $myHtml =  $myBlock->toHtml();
                    return $this->getResponse()->setBody($myHtml);
                }
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
        } else {
            $this->_getSession()->addError($this->__('No valid data sent'));
        }
        $this->_redirect('*/*/index');
    }

}