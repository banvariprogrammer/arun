<?php
namespace Ambab\BankDiscount\Model;

use Ambab\BankDiscount\Api\ValidateAddressRepositoryInterface;
/**
 * Class ValidateAddressRepository
 */
class ValidateAddressRepository implements ValidateAddressRepositoryInterface
{
    protected $_request;
    protected $_storeManager;
    protected $_resource;
    protected $logger;

    public function __construct(\Magento\Framework\App\Request\Http $Request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resource
        )
    {
        $this->_request = $Request;
        $this->_storeManager=$storeManager;
        $this->_resource = $resource;
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/validateAddress.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);
    }

    public function validateAddress()
    {
        $detials = '';
        $this->logger->info('Info API execution start');
        $requestData = $this->_request->getContent();
        $requestDataObj = json_decode($requestData,True);  
        unset($requestDataObj['default_billing']);
        unset($requestDataObj['default_shipping']);
        $requiredFields = array_keys($requestDataObj,'');
        $this->logger->info('Request Data: '.print_r(json_encode($requestDataObj),1));
        if(count($requiredFields) > 0){
            $errorFields = array('code' => 'checkout-100', 'parameters' => $requiredFields,'message' => 'Some of the fields are not valid');
            $detials = array('success' => false,'error'=> $errorFields);
        }else{
            $sql = "SELECT area FROM area WHERE area = '".$requestDataObj['area']."' ";
            $area = $this->_resource->getConnection()->query($sql)->fetchColumn();
            $sql1 = "SELECT city FROM area WHERE city = '".$requestDataObj['city']."' ";
            $city = $this->_resource->getConnection()->query($sql1)->fetchColumn();
            if(empty($area)){
                $errorFields = array('code' => 'checkout-100', 'parameters' => array('area'),'message' => 'Area is not valid');
            }
            if(empty($city)){
                $errorFields = array('code' => 'checkout-100', 'parameters' => array('city'),'message' => 'City is not valid');
            }
            if(strlen($requestDataObj['street']) < 3){
                $errorFields = array('code' => 'checkout-100', 'parameters' => array('street'),'message' => 'Please enter at least 3 characters.');
            }
            if((($requestDataObj['country_code'] == 'AE' || $requestDataObj['country_code'] == 'SA') && strlen($requestDataObj['phone']) != 13) || ($requestDataObj['country_code'] == 'KW') && strlen($requestDataObj['phone']) != 12){
                $errorFields = array('code' => 'checkout-100', 'parameters' => array('phone'),'message' => 'Please enter proper phone number.');
            }
            if(!empty($errorFields)){
                http_response_code(400);
                $detials = array('success' => false,'error'=> $errorFields);
            }else{

                $detials = array('success' => true);
            }
        }
        $this->logger->info('Response Data: '.print_r(json_encode($detials),1));
        $this->logger->info('Info API execution end');
        echo  json_encode($detials);exit;
    }
}
