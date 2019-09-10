<?php
namespace Ambab\BankDiscount\Model\ResourceModel;

class Bankdiscount extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('bank_discount_details', 'bank_discount_id');
    }
}
?>