<?php
namespace Ambab\BankDiscount\Model;

class Bankdiscount extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Ambab\BankDiscount\Model\ResourceModel\Bankdiscount');
    }
}
?>