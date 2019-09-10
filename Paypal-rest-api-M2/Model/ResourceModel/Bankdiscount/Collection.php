<?php

namespace Ambab\BankDiscount\Model\ResourceModel\Bankdiscount;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Ambab\BankDiscount\Model\Bankdiscount', 'Ambab\BankDiscount\Model\ResourceModel\Bankdiscount');
        $this->_map['fields']['page_id'] = 'main_table.page_id';
    }

}
?>