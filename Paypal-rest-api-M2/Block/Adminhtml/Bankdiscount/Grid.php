<?php
namespace Ambab\BankDiscount\Block\Adminhtml\Bankdiscount;

class Grid extends \Magento\Backend\Block\Widget\Grid\Extended
{
    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * @var \Ambab\BankDiscount\Model\bankdiscountFactory
     */
    protected $_bankdiscountFactory;

    /**
     * @var \Ambab\BankDiscount\Model\Status
     */
    protected $_status;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \Ambab\BankDiscount\Model\bankdiscountFactory $bankdiscountFactory
     * @param \Ambab\BankDiscount\Model\Status $status
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Ambab\BankDiscount\Model\BankdiscountFactory $BankdiscountFactory,
        \Ambab\BankDiscount\Model\Status $status,
        \Magento\Framework\Module\Manager $moduleManager,
        array $data = []
    ) {
        $this->_bankdiscountFactory = $BankdiscountFactory;
        $this->_status = $status;
        $this->moduleManager = $moduleManager;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('postGrid');
        $this->setDefaultSort('bank_discount_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(false);
        $this->setVarNameFilter('post_filter');
    }

    /**
     * @return $this
     */
    protected function _prepareCollection()
    {
        $collection = $this->_bankdiscountFactory->create()->getCollection();
        $this->setCollection($collection);

        parent::_prepareCollection();

        return $this;
    }

    /**
     * @return $this
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'bank_discount_id',
            [
                'header' => __('ID'),
                'type' => 'number',
                'index' => 'bank_discount_id',
                'header_css_class' => 'col-id',
                'column_css_class' => 'col-id'
            ]
        );


		
				$this->addColumn(
					'bank_name',
					[
						'header' => __('Bank Name'),
						'index' => 'bank_name',
					]
				);
				
				$this->addColumn(
					'bin_number',
					[
						'header' => __('Bin Number'),
						'index' => 'bin_number',
					]
				);
				
				$this->addColumn(
					'country',
					[
						'header' => __('Country'),
						'index' => 'country',
					]
				);
				


		
        //$this->addColumn(
            //'edit',
            //[
                //'header' => __('Edit'),
                //'type' => 'action',
                //'getter' => 'getId',
                //'actions' => [
                    //[
                        //'caption' => __('Edit'),
                        //'url' => [
                            //'base' => '*/*/edit'
                        //],
                        //'field' => 'bank_discount_id'
                    //]
                //],
                //'filter' => false,
                //'sortable' => false,
                //'index' => 'stores',
                //'header_css_class' => 'col-action',
                //'column_css_class' => 'col-action'
            //]
        //);
		

		
		   $this->addExportType($this->getUrl('bankdiscount/*/exportCsv', ['_current' => true]),__('CSV'));
		   $this->addExportType($this->getUrl('bankdiscount/*/exportExcel', ['_current' => true]),__('Excel XML'));

        $block = $this->getLayout()->getBlock('grid.bottom.links');
        if ($block) {
            $this->setChild('grid.bottom.links', $block);
        }

        return parent::_prepareColumns();
    }

	
    /**
     * @return $this
     */
    protected function _prepareMassaction()
    {

        $this->setMassactionIdField('bank_discount_id');
        //$this->getMassactionBlock()->setTemplate('Ambab_BankDiscount::bankdiscount/grid/massaction_extended.phtml');
        $this->getMassactionBlock()->setFormFieldName('bankdiscount');

        $this->getMassactionBlock()->addItem(
            'delete',
            [
                'label' => __('Delete'),
                'url' => $this->getUrl('bankdiscount/*/massDelete'),
                'confirm' => __('Are you sure?')
            ]
        );

        $statuses = $this->_status->getOptionArray();

        $this->getMassactionBlock()->addItem(
            'status',
            [
                'label' => __('Change status'),
                'url' => $this->getUrl('bankdiscount/*/massStatus', ['_current' => true]),
                'additional' => [
                    'visibility' => [
                        'name' => 'status',
                        'type' => 'select',
                        'class' => 'required-entry',
                        'label' => __('Status'),
                        'values' => $statuses
                    ]
                ]
            ]
        );


        return $this;
    }
		

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('bankdiscount/*/index', ['_current' => true]);
    }

    /**
     * @param \Ambab\BankDiscount\Model\bankdiscount|\Magento\Framework\Object $row
     * @return string
     */
    public function getRowUrl($row)
    {
		
        return $this->getUrl(
            'bankdiscount/*/edit',
            ['bank_discount_id' => $row->getId()]
        );
		
    }

	

}