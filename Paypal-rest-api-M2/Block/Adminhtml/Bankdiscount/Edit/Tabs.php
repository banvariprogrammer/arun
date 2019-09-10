<?php
namespace Ambab\BankDiscount\Block\Adminhtml\Bankdiscount\Edit;

/**
 * Admin page left menu
 */
class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('bankdiscount_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Bank Bin Information'));
    }
}