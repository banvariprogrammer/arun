<?php

namespace Ambab\BankDiscount\Controller\Adminhtml\bankdiscount;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var PageFactory
     */
    protected $resultPagee;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Index action
     *
     * @return void
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ambab_BankDiscount::bankdiscount');
        $resultPage->addBreadcrumb(__('Ambab'), __('Ambab'));
        $resultPage->addBreadcrumb(__('Manage Details'), __('Manage Bank Discount Details'));
        $resultPage->getConfig()->getTitle()->prepend(__('Manage Bank Discount Details'));

        return $resultPage;
    }
}
?>