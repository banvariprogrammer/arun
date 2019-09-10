<?php
namespace Ambab\BankDiscount\Model;
use Ambab\BankDiscount\Api\CreateOrderRepositoryInterface;
use Ambab\RestApi\Helper\CustomTools;
/**
 * Class CreateOrderRepository
 */
class CreateOrderRepository implements CreateOrderRepositoryInterface
{
    const BIN_PREFIX = "6thstreetbin";

    protected $_request;
    protected $logger;
    protected $_customHelper;
    protected $quoteFactory;
    protected $quoteRepository;
    protected $orderRepository;
    protected $_bankdiscountFactory;
    protected $_storeManager;
    protected $_scopeConfig;
    protected $_resource;
    protected $coupon;
    protected $saleRule;
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    /*repository*/
    public function __construct(\Magento\Framework\App\Request\Http $Request,
        CustomTools $customHelper,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Ambab\BankDiscount\Model\BankdiscountFactory $bankdiscountFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\SalesRule\Model\Coupon $coupon,
        \Magento\SalesRule\Model\Rule $saleRule
        )
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_request = $Request;
        $this->_customHelper   = $customHelper;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->quoteFactory = $quoteFactory;
        $this->_bankdiscountFactory = $bankdiscountFactory;
        $this->_storeManager=$storeManager;
        $this->_scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/createOrderAPIDetails.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);
        $this->_resource      = $objectManager->get('Magento\Framework\App\ResourceConnection');

        $this->coupon = $coupon;
        $this->saleRule = $saleRule;
    }
    public function createOrder()
    {
        $checkout_payment_success_code = '10000';
        $this->logger->info('Info API execution start');
        $requestData = $this->_request->getContent();
        $requestDataObj = json_decode($requestData);
        $cart_id = $this->_customHelper->getQuoteFromMask($requestDataObj->cart_id,'quote_id_mask');
        $this->logger->info('Request Data: '.print_r(json_encode($requestDataObj),1));
        
        if(empty($cart_id)){
            $cart_id = $requestDataObj->cart_id;
        }
        
        $quote = $this->quoteFactory->create()->load($cart_id);
        $address = array('region' => 0,'region_id' => 0,'region_code' => 0,'country_id' => $requestDataObj->address->country_code,'street' => array($requestDataObj->address->street),'telephone' => $requestDataObj->address->phone,'postcode' => $requestDataObj->address->area,'city' => $requestDataObj->address->city,'firstname' => $requestDataObj->address->firstname,'lastname' => $requestDataObj->address->lastname,'email' => $requestDataObj->address->email,'sameAsBilling' => 0);
        $shipAddress = array('region' => 0,'region_id' => 0,'region_code' => 0,'country_id' => $requestDataObj->address->country_code,'street' => array($requestDataObj->address->street),'telephone' => $requestDataObj->address->phone,'postcode' => $requestDataObj->address->area,'city' => $requestDataObj->address->city,'firstname' => $requestDataObj->address->firstname,'lastname' => $requestDataObj->address->lastname,'email' => $requestDataObj->address->email);
                $shipAddress = array('shippingAddress' => $shipAddress, 'shippingCarrierCode'=>'fetchr','shippingMethodCode'=>'fetchr');
                $methodInfo = array('method' => array('method' => $requestDataObj->payment_method_code));
        $shippingRequest = array("addressInformation"=>$shipAddress);
        $billingRequest = array("address"=>$address,'useForShipping' => True);
        
        if (is_null($quote->getCustomerId())) {// For Guest user
            $maskedId     = $requestDataObj->cart_id;
            // 1. Billing API.
            $billingAPI = $this->_request->getDistroBaseUrl().'rest/V1/guest-carts/cart_id/billing-address';
            $billingAPI = str_replace('cart_id',$maskedId,$billingAPI);
            
            $billRes = $this->getCurlData($billingAPI,json_encode($billingRequest),'POST',$authToken='');
            $this->logger->info('billRes Data: '.print_r(json_encode($billRes),1));
            if(!isset($billRes->message)){
                //2. Shipping API.
                $shippingAPI = $this->_request->getDistroBaseUrl().'rest/V1/guest-carts/cart_id/shipping-information';
                $shippingAPI = str_replace('cart_id',$maskedId,$shippingAPI);
                $shippRes = $this->getCurlData($shippingAPI,json_encode($shippingRequest),'POST',$authToken='');
                $this->logger->info('shippRes Data: '.print_r(json_encode($shippRes),1));
                if(!isset($shippRes->message)){
                    // 3. Payment
                    $paymentAPI = $this->_request->getDistroBaseUrl().'rest/V1/guest-carts/cart_id/selected-payment-method';
                    $paymentAPI = str_replace('cart_id',$maskedId,$paymentAPI);
                    $paymentRes = $this->getCurlData($paymentAPI,json_encode($methodInfo),'PUT',$authToken='');
                    $this->logger->info('paymentRes Data: '.print_r(json_encode($paymentRes),1));

                    if(!isset($paymentRes->message)){
                        // 4. OrderPlace.
                        $binPromoCode = '';
                        if(isset($requestDataObj->bin_number)){
                            $website = $this->_storeManager->getWebsite()->getName();
                            $currentCountry = preg_replace('/\s+/', '_', $website);
                            $bankDetails = $this->_bankdiscountFactory->create();
                            $bankDetailsColl = $bankDetails->getCollection()->addFieldToFilter('bin_number', array('eq' => $requestDataObj->bin_number))->addFieldToFilter('country', array('eq' => $website))->getData();
                            if(count($bankDetailsColl)>0){
                                $bankCountry = preg_replace('/\s+/', '_', $bankDetailsColl[0]['country']);
                                if($currentCountry == $bankCountry){
                                    
                                    if(count($bankDetailsColl)>0){
                                        $discountAPIUrl = $this->_request->getDistroBaseUrl().'rest/V1/guest-carts/id/coupons/couponcode';
                                        $discountAPIUrl = str_replace('id',$maskedId,$discountAPIUrl);
                                        $bankName = preg_replace('/\s+/', '_', $bankDetailsColl[0]['bank_name']);
                                        $discountAPIUrl = str_replace('couponcode',self::BIN_PREFIX.'_'. $bankName . '_' . $bankCountry,$discountAPIUrl);
                                        $appliedDiscountAmount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
                                        $ruleId =   $this->coupon->loadByCode(self::BIN_PREFIX.'_'. $bankName . '_' . $bankCountry)->getRuleId();
                                        $binDiscountAmount = 0;
                                        $rule = $this->saleRule->load($ruleId);
                                        $this->logger->info('BIN RULE Active :: '. $rule->getIsActive());
                                        if($rule->getIsActive()){
                                            $this->logger->info('BIN RULEID :: '. $ruleId);
                                            $this->logger->info('BIN COUPON NAME:: '. self::BIN_PREFIX.'_' . $bankName . '_' . $bankCountry);
                                            $this->logger->info('Applied rule discount :: '. $appliedDiscountAmount);
                                            if($rule->getSimpleAction() == 'by_percent'){

                                                $binDiscountAmount = ($rule->getDiscountAmount() / 100) * round($quote->getSubtotal());
                                            }else{
                                                $binDiscountAmount = $rule->getDiscountAmount();
                                            }
                                            $this->logger->info('Bin discount :: '. $binDiscountAmount);
                                            if(round($appliedDiscountAmount) <= $binDiscountAmount){
                                                $this->logger->info('Bin discount is grator than applied discount');
                                                $this->applyCoupon($discountAPIUrl,$token ='guest');
                                                $binPromoCode = self::BIN_PREFIX.'_' . $bankName . '_' . $bankCountry;
                                            }else{
                                                $binPromoCode = '';
                                            }
                                        }else{
                                            $binPromoCode = '';
                                        }
                                    }
                                }
                            }
                        }
                        $orderAPI = $this->_request->getDistroBaseUrl().'rest/V1/guest-carts/cart_id/order';
                        $orderAPI = str_replace('cart_id',$maskedId,$orderAPI);
                        $orderRes = $this->getCurlData($orderAPI,json_encode(array('source' => 'MOBILEAPP')),'PUT',$authToken='');
                        $this->logger->info('orderRes Data: '.print_r(json_encode($orderRes),1));

                        if(!isset($orderRes->message)){

                            if($requestDataObj->payment_method_code == 'paypal'){
                                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                                $helper = $objectManager->get('Ambab\BankDiscount\Helper\Data');
                                $values = $helper->paypaldata($requestDataObj);
                                echo $values;die;
                            }

                            if($requestDataObj->payment_method_code == 'checkout'){
                                $connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
                                $id = json_decode($orderRes, 1);
                                $order = $this->orderRepository->get($id);
                                $orderAmount = $order->getGrandTotal() * 100;
                                if($order->getOrderCurrencyCode() == 'KWD' || $order->getOrderCurrencyCode() == 'OMR' || $order->getOrderCurrencyCode() == 'BHD'){
                                    $orderAmount = $order->getGrandTotal() * 1000;
                                }
                                $reqdata = array('email' => $order->getCustomerEmail(),'value' => $orderAmount,'currency' => $order->getOrderCurrencyCode(),'trackId' => $order->getIncrementId(),'cardToken' => $requestDataObj->card_token);
                                $this->logger->info('Req Data For Checkout: '.print_r(json_encode($reqdata),1));
                                $chargeData = $this->chargePaymentForCheckout(json_encode($reqdata));
                                $checkRres = json_decode($chargeData,true);
                                $this->logger->info('Response Data from Checkout: '.print_r(json_encode($checkRres),1));
                                
                                if(isset($checkRres['responseCode'])){
                                    if($checkRres['responseCode'] == $checkout_payment_success_code){
                                        $orderData = $this->getOrderData(json_decode($orderRes));
                                        $orderData = json_decode($orderData,true);
                                        $details= array('success' => true,'data'=>$orderData[trim($orderRes,'"')]);
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'success');
                                        echo  json_encode($details);exit;
                                    }else{
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'failed');
                                        $details= array('success' => false,'error'=>$checkRres['responseMessage'],'error_code'=>$checkRres['responseCode']);
                                        $reStoreQuote = $this->quoteRepository->get($cart_id);
                                        $reStoreQuote->setIsActive(1)->setReservedOrderId(null);
                                        $this->quoteRepository->save($reStoreQuote);
                                        if($binPromoCode !=''){
                                            $this->logger->info('==== DELETE COUPON FROM CART ==='.$binPromoCode);
                                            $this->deleteCouponFromCart($binPromoCode,$token ='guest',$requestDataObj->cart_id);
                                        }
                                        echo json_encode($details);exit;
                                    }
                                }else{
                                    if(isset($checkRres['errorCode'])){
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'failed');
                                        $details= array('success' => false,'error'=>$checkRres['message'],'error_code'=>$checkRres['errorCode']);
                                        $reStoreQuote = $this->quoteRepository->get($cart_id);
                                        $reStoreQuote->setIsActive(1)->setReservedOrderId(null);
                                        $this->quoteRepository->save($reStoreQuote);
                                        if($binPromoCode !=''){
                                            $this->logger->info('==== DELETE COUPON FROM CART ==='.$binPromoCode);
                                            $this->deleteCouponFromCart($binPromoCode,$token ='guest',$requestDataObj->cart_id);
                                        }
                                        echo json_encode($details);exit;
                                    }else{
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'failed');
                                        $details= array('success' => false,'error'=>'Bad error','error_code'=>400);
                                        $reStoreQuote = $this->quoteRepository->get($cart_id);
                                        $reStoreQuote->setIsActive(1)->setReservedOrderId(null);
                                        $this->quoteRepository->save($reStoreQuote);
                                        if($binPromoCode !=''){
                                            $this->logger->info('==== DELETE COUPON FROM CART ==='.$binPromoCode);
                                            $this->deleteCouponFromCart($binPromoCode,$token ='guest',$requestDataObj->cart_id);
                                        }
                                        echo json_encode($details);exit;
                                    }
                                }
                            }else{
                                $orderData = $this->getOrderData(json_decode($orderRes));
                                $orderData = json_decode($orderData,true);
                                    $details= array('success' => true,'data'=>$orderData[trim($orderRes,'"')]);
                                echo  json_encode($details);exit;
                            }
                        }else{
                            $details= array('success' => false,'error'=> $orderRes->message);
                         echo  json_encode($details);exit;
                        }
                    }else{
                         $details= array('success' => false,'error'=> $paymentRes->message);
                         echo  json_encode($details);exit;
                    }
                }else{
                    $details= array('success' => false,'error'=> $shippRes->message);
                    echo  json_encode($details);exit;
                }
            }else{
                $details= array('success' => false,'error'=> $billRes->message);
                echo  json_encode($details);exit;
            }
        }else{
            $authToken    = $this->_request->getHeader('Authorization');
            $billingAPI = $this->_request->getDistroBaseUrl().'rest/V1/carts/mine/billing-address';
            $billRes = $this->getCurlData($billingAPI,json_encode($billingRequest),'POST',$authToken);
            $this->logger->info('billRes Data: '.print_r(json_encode($billRes),1));
            if(!isset($billRes->message)){
                $shippingAPI = $this->_request->getDistroBaseUrl().'rest/V1/carts/mine/shipping-information';
                $shippRes = $this->getCurlData($shippingAPI,json_encode($shippingRequest),'POST',$authToken);
                $this->logger->info('shippRes Data: '.print_r(json_encode($shippRes),1));
                if(!isset($shippRes->message)){
                    $paymentAPI = $this->_request->getDistroBaseUrl().'rest/V1/carts/mine/selected-payment-method';
                    $paymentRes = $this->getCurlData($paymentAPI,json_encode($methodInfo),'PUT',$authToken);
                    $this->logger->info('paymentRes Data: '.print_r(json_encode($paymentRes),1));
                    //print_r($paymentRes);exit;
                    if(!isset($paymentRes->message)){
                        // 4. OrderPlace.
                        $binPromoCode = '';
                        if(isset($requestDataObj->bin_number)){
                            $website = $this->_storeManager->getWebsite()->getName();
                            $currentCountry = preg_replace('/\s+/', '_', $website);
                            $bankDetails = $this->_bankdiscountFactory->create();
                            $bankDetailsColl = $bankDetails->getCollection()->addFieldToFilter('bin_number', array('eq' => $requestDataObj->bin_number))->addFieldToFilter('country', array('eq' => $website))->getData();
                            if(count($bankDetailsColl)>0){
                                $bankCountry = preg_replace('/\s+/', '_', $bankDetailsColl[0]['country']);
                                if($currentCountry == $bankCountry){
                                    if(count($bankDetailsColl)>0){
                                        $discountAPIUrl      = $this->_storeManager->getStore()->getBaseUrl().'rest/V1/carts/mine/coupons/couponcode';
                                        $bankName = preg_replace('/\s+/', '_', $bankDetailsColl[0]['bank_name']);
                                        $discountAPIUrl = str_replace('couponcode',self::BIN_PREFIX.'_' . $bankName . '_' . $bankCountry,$discountAPIUrl);
                                        $appliedDiscountAmount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
                                        $ruleId =   $this->coupon->loadByCode(self::BIN_PREFIX.'_' . $bankName . '_' . $bankCountry)->getRuleId();
                                        $binDiscountAmount = 0;
                                        $rule = $this->saleRule->load($ruleId);
                                        $this->logger->info('BIN RULE Active :: '. $rule->getIsActive());
                                        if($rule->getIsActive()){
                                            $this->logger->info('BIN RULEID :: '. $ruleId);
                                            $this->logger->info('BIN COUPON NAME:: '. self::BIN_PREFIX.'_' . $bankName . '_' . $bankCountry);
                                            $this->logger->info('Applied rule discount :: '. $appliedDiscountAmount);
                                            if($rule->getSimpleAction() == 'by_percent'){
                                                $binDiscountAmount = ($rule->getDiscountAmount() / 100) * round($quote->getSubtotal());
                                            }else{
                                                $binDiscountAmount = $rule->getDiscountAmount();
                                            }
                                            $this->logger->info('Bin discount :: '. $binDiscountAmount);
                                            if(round($appliedDiscountAmount) <= $binDiscountAmount){
                                                $this->logger->info('Bin discount is grator than applied discount');
                                                $this->applyCoupon($discountAPIUrl,$authToken);
                                                $binPromoCode = self::BIN_PREFIX.'_' . $bankName . '_' . $bankCountry;
                                            }else{
                                                $binPromoCode = '';
                                            }
                                        }else{
                                            $binPromoCode = '';
                                        }
                                    }
                                }
                            }
                        }
                        $orderAPI = $this->_request->getDistroBaseUrl().'rest/V1/carts/mine/order';
                        $orderRes = $this->getCurlData($orderAPI,json_encode(array('source' => 'MOBILEAPP')),'PUT',$authToken);
                        $this->logger->info('orderRes Data: '.print_r(json_encode($orderRes),1));
                        if(!isset($orderRes->message)){

                            if($requestDataObj->payment_method_code == 'paypal'){
                                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                                $helper = $objectManager->get('Ambab\BankDiscount\Helper\Data');
                                $values = $helper->paypaldata($requestDataObj, $quote);
                                echo $values;die;
                            }

                            if($requestDataObj->payment_method_code == 'checkout'){
                                $connection = $this->_resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
                                $id = json_decode($orderRes, 1);
                                $order = $this->orderRepository->get($id);
                                $orderAmount = $order->getGrandTotal() * 100;
                                if($order->getOrderCurrencyCode() == 'KWD' || $order->getOrderCurrencyCode() == 'OMR' || $order->getOrderCurrencyCode() == 'BHD'){
                                    $orderAmount = $order->getGrandTotal() * 1000;
                                }
                                $reqdata = array('email' => $order->getCustomerEmail(),'value' => $orderAmount,'currency' => $order->getOrderCurrencyCode(),'trackId' => $order->getIncrementId(),'cardToken' => $requestDataObj->card_token);
                                $this->logger->info('Req Data For Checkout: '.print_r(json_encode($reqdata),1));
                                $chargeData = $this->chargePaymentForCheckout(json_encode($reqdata));
                                $this->logger->info('Response Data from Checkout: '.print_r(json_encode($chargeData),1));
                                $checkRres = json_decode($chargeData,true);
                                
                                if(isset($checkRres['responseCode'])){
                                    if($checkRres['responseCode'] == $checkout_payment_success_code){
                                        $orderData = $this->getOrderData(json_decode($orderRes));
                                        $orderData = json_decode($orderData,true);
                                        $details= array('success' => true,'data'=>$orderData[trim($orderRes,'"')]);
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'success');
                                        echo  json_encode($details);exit;
                                    }else{
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'failed');
                                        $details= array('success' => false,'error'=>$checkRres['responseMessage'],'error_code'=>$checkRres['responseCode']);
                                        $reStoreQuote = $this->quoteRepository->get($cart_id);
                                        $reStoreQuote->setIsActive(1)->setReservedOrderId(null);
                                        $this->quoteRepository->save($reStoreQuote);
                                        if($binPromoCode !=''){
                                            $this->logger->info('==== DELETE COUPON FROM CART ==='.$binPromoCode);
                                            $this->deleteCouponFromCart($binPromoCode,$authToken,$cart_id);
                                        }
                                        echo json_encode($details);exit;
                                    }
                                }else{
                                    if(isset($checkRres['errorCode'])){
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'failed');
                                        $details= array('success' => false,'error'=>$checkRres['message'],'error_code'=>$checkRres['errorCode']);
                                        $reStoreQuote = $this->quoteRepository->get($cart_id);
                                        $reStoreQuote->setIsActive(1)->setReservedOrderId(null);
                                        $this->quoteRepository->save($reStoreQuote);
                                        if($binPromoCode !=''){
                                            $this->logger->info('==== DELETE COUPON FROM CART ==='.$binPromoCode);
                                            $this->deleteCouponFromCart($binPromoCode,$authToken,$cart_id);
                                        }
                                        echo json_encode($details);exit;
                                    }else{
                                        $this->updateOrderStatus($connection, json_decode($orderRes), 'failed');
                                        $details= array('success' => false,'error'=>'Bad error','error_code'=>400);
                                        $reStoreQuote = $this->quoteRepository->get($cart_id);
                                        $reStoreQuote->setIsActive(1)->setReservedOrderId(null);
                                        $this->quoteRepository->save($reStoreQuote);
                                        if($binPromoCode !=''){
                                            $this->logger->info('==== DELETE COUPON FROM CART ==='.$binPromoCode);
                                            $this->deleteCouponFromCart($binPromoCode,$authToken,$cart_id);
                                        }
                                        echo json_encode($details);exit;
                                    }
                                }
                            }else{
                                $orderData = $this->getOrderData(json_decode($orderRes));
                                $orderData = json_decode($orderData,true);
                                $details= array('success' => true,'data'=>$orderData[trim($orderRes,'"')]);
                                echo  json_encode($details);exit;
                            }
                        }else{
                            $details= array('success' => false,'error'=> $orderRes->message);
                         echo  json_encode($details);exit;
                        }
                        
                    }else{
                         $details= array('success' => false,'error'=> $paymentRes->message);
                         echo  json_encode($details);exit;
                    }
                }else{
                    $details= array('success' => false,'error'=> $shippRes->message);
                    echo  json_encode($details);exit;
                }
            }else{
                $details= array('success' => false,'error'=> $billRes->message);
                echo  json_encode($details);exit;
            }
        }
        //print($quote->getData());exit;
        $this->logger->info('Info API execution end');
    }
    public function getCurlData($url,$data,$method,$token=''){
        if($method == 'POST'){
            $header = array( 'Accept: application/json', 'Content-Type: application/json','Request-Source: MobileApp', 'Authorization:'. $token );
             $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            return $response  = curl_exec($ch);
            curl_close($ch);
        }else{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            if(!empty($token)){
                $header = array( 'Accept: application/json','Request-Source: MobileApp', 'Content-Type: application/json', 'Authorization:'. $token );
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }else{
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Request-Source: MobileApp','Content-Length: ' . strlen($data)));
            }
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            return $response  = curl_exec($ch);
            curl_close($ch);
        }
    }
    public function chargePaymentForCheckout($data){
        $this->logger->info('::::Checkout API CALL START::::');
        $ch = curl_init();
        $checkoutAPIUrl = $this->_scopeConfig->getValue('ambab_mobilecheckout/general/checkout_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $secretKey = $this->_scopeConfig->getValue('ambab_mobilecheckout/general/checkout_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $header = array( 'Accept: application/json', 'Content-Type: application/json', 'Authorization:'. $secretKey );
        $this->logger->info('checkout API url:'.$checkoutAPIUrl);
        curl_setopt($ch, CURLOPT_URL, $checkoutAPIUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $this->logger->info('::::Checkout API CALL END::::');
        return $response  = curl_exec($ch);
        curl_close($ch);
    }
    public function getOrderData($id){
        $this->logger->info("Request Order id :: " . $id);
        $ch = curl_init();
        $url = $this->_request->getDistroBaseUrl().'/rest/V1/order/mine/:orderId';
        $url = str_replace(':orderId',$id,$url);
        $authToken    = $this->_request->getHeader('Authorization');
        $header = array( 'Accept: application/json', 'Content-Type: application/json', 'Authorization:'. $authToken );
        //print_r($header);exit();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        $this->logger->info("Get order API response :: " . $response);
        return $response;
        curl_close($ch);
    }
    public function applyCoupon($discountAPIUrl,$authToken){
        $ch = curl_init($discountAPIUrl);
        curl_setopt($ch, CURLOPT_PUT, true);
        if ($authToken != 'guest') {
            $header = array( 'Accept: application/json', 'Content-Type: application/x-www-form-urlencoded','Request-Source: MobileApp', 'Authorization:'. $authToken );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $response = curl_exec($ch);
    }
    public function deleteCouponFromCart($couponCode,$authToken,$cartId){
        if ($authToken == 'guest') {
            $url = $this->_request->getDistroBaseUrl().'/rest/V1/guest-carts/'.$cartId.'/coupons/';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            return $response = curl_exec($ch);
        }else{
            $url = $this->_request->getDistroBaseUrl().'/rest/V1/carts/mine/coupons/';
            $ch = curl_init($url);
            $header = array( 'Accept: application/json', 'Content-Type: application/x-www-form-urlencoded','Request-Source: MobileApp', 'Authorization:'. $authToken );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            return $response = curl_exec($ch);
        }
    }
    public function updateOrderStatus($connection, $orderId, $paymentStatus) {
        if ($paymentStatus != 'success' && $paymentStatus != 'failed' )
            return;
        if($paymentStatus == 'success') {
            $state = 'processing';
            $status = 'payment_success';          
        }
        else if($paymentStatus == 'failed') {
            $state = 'payment_aborted';
            $status = 'payment_aborted';
        }
        $connection->rawQuery("UPDATE sales_order 
                                SET state = " . "'$state'," . "status = " . "'$status'" . " 
                                WHERE entity_id = ". $orderId);
        $connection->rawQuery("UPDATE sales_order_grid 
                                SET status = " . "'$status'" . " 
                                WHERE entity_id = ". $orderId);
    }
}
