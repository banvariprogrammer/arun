<?php
namespace Ambab\BankDiscount\Model;

use Ambab\BankDiscount\Api\CustombankDiscountRepositoryInterface;
use Ambab\RestApi\Helper\CustomTools;
/**
 * Class CustomOrderRepository
 * @param \Magento\Quote\Model\Quote\Address\Total $total
 */
class CustombankDiscountRepository implements CustombankDiscountRepositoryInterface
{
    const BIN_PREFIX = "6thstreetbin";

    protected $_request;
    protected $_storeManager;
    protected $_resource;
    protected $logger;
    protected $_bankdiscountFactory;
    protected $_customHelper;
    protected $quoteFactory;
    protected $coupon;
    protected $saleRule;     

    public function __construct(\Magento\Framework\App\Request\Http $Request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resource,
        CustomTools $customHelper,
        \Ambab\BankDiscount\Model\BankdiscountFactory $bankdiscountFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\SalesRule\Model\Coupon $coupon,
        \Magento\SalesRule\Model\Rule $saleRule,
        \Magento\Quote\Model\Quote\Address\Total $total
        )
    {
        $this->_request = $Request;
        $this->_storeManager=$storeManager;
        $this->_resource = $resource;
        $this->_customHelper   = $customHelper;
        $this->_bankdiscountFactory = $bankdiscountFactory;
        $this->quoteFactory = $quoteFactory;
        $this->coupon = $coupon;
        $this->saleRule = $saleRule;
        $this->total = $total;
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/bankDiscountDetails.log');
        $this->logger = new \Zend\Log\Logger();
        $this->logger->addWriter($writer);
    }

    public function getBinDetails()
    {
        $this->logger->info('Info API execution start');
        $requestData = $this->_request->getContent();
        $requestDataObj = json_decode($requestData);
        $bankDetails = $this->_bankdiscountFactory->create();
        $website = $this->_storeManager->getWebsite()->getName();
        $currentCountry = preg_replace('/\s+/', '_', $website);
        $bankDetailsColl = $bankDetails->getCollection()->addFieldToFilter('bin_number', array('eq' => $requestDataObj->bin_number))->addFieldToFilter('country', array('eq' => $website))->getData();
        //echo count($bankDetailsColl);exit;
        $cart_id = $this->_customHelper->getQuoteFromMask($requestDataObj->cart_id,'quote_id_mask');
        if(empty($cart_id)){
            $cart_id = $requestDataObj->cart_id;
        }
        $quote = $this->quoteFactory->create()->load($cart_id);
        if(count($bankDetailsColl)>0){
            if(count($quote->getSubtotal()) > 0){
               
                $bankCountry = preg_replace('/\s+/', '_', $bankDetailsColl[0]['country']);
                if($currentCountry == $bankCountry){
                    $discountAPIUrl = '';
                    $bankName = preg_replace('/\s+/', '_', $bankDetailsColl[0]['bank_name']);
                    $ruleId =   $this->coupon->loadByCode(self::BIN_PREFIX.'_'. $bankName . '_' . $bankCountry)->getRuleId();
                    $rule = $this->saleRule->load($ruleId);
                    $this->logger->info('BIN RULE Active :: '. $rule->getIsActive());
                    if($rule->getIsActive()){
                    $discountAmount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
                    $this->logger->info('BIN RULEID :: '. $ruleId);
                    $this->logger->info('BIN COUPON NAME:: '.self::BIN_PREFIX.'_'. $bankName . '_' . $bankCountry);
                    $this->logger->info('Applied rule discount :: '. $discountAmount);
                    if($rule->getSimpleAction() == 'by_percent'){
                        $discount = ($rule->getDiscountAmount() / 100) * round($quote->getSubtotal());
                        $this->logger->info('Bin discount by percent :: '. $discount);
                        if($discountAmount > 0 ){
                            if(round($discountAmount) <= $discount){
                                $this->logger->info('Bin discount greator than applied discount');
                                $cartData = array('discount' => - round($discount),'total'=> round($quote->getSubtotal()) - round($discount),'subtotal'=> round($quote->getSubtotal()), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => $bankDetailsColl[0]['bank_name']);
                                $detials = array('success' => true,'is_BIN_promo_applied' => true,'data'=>$cartData);
                                    echo  json_encode($detials);exit;
                            }else{
                                $this->logger->info('Bin discount less than applied discount');
                                $cartData = array('discount' => -round($discountAmount),'total'=> round($quote->getSubtotalWithDiscount()),'subtotal'=> round($quote->getSubtotal()), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => $bankDetailsColl[0]['bank_name']);
                                $detials = array('success' => true,'is_BIN_promo_applied' => false,'data'=>$cartData);
                                echo  json_encode($detials);exit;
                            }
                        }else{
                            $cartData = array('discount' => -round($discount),'total'=> round($quote->getSubtotal()) - round($discount),'subtotal'=> round($quote->getSubtotal()), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => $bankDetailsColl[0]['bank_name']);
                            $detials = array('success' => true,'is_BIN_promo_applied' => true,'data'=>$cartData);
                                    echo  json_encode($detials);exit;
                        }
                    }else{
                        $discount = $rule->getDiscountAmount();
                        $this->logger->info('Bin discount by fix :: '. $discount);
                        if($discountAmount > 0 ){
                            if(round($discountAmount) <= $discount){
                            $this->logger->info('Bin discount greator than applied discount');
                            $cartData = array('discount' => -round($discount),'total'=> round($quote->getSubtotal()) - round($discount),'subtotal'=> round($quote->getSubtotal()), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => $bankDetailsColl[0]['bank_name']);
                            $detials = array('success' => true, 'is_BIN_promo_applied' => true,'data'=>$cartData);
                                echo  json_encode($detials);exit;
                            }else{
                                $this->logger->info('Bin discount less than applied discount');
                                $cartData = array('discount' => -round($discountAmount),'total'=> round($quote->getSubtotalWithDiscount()),'subtotal'=> round($quote->getSubtotal()), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => $bankDetailsColl[0]['bank_name']);
                                $detials = array('success' => true,'is_BIN_promo_applied' => false, 'data'=>$cartData);
                                echo  json_encode($detials);exit;
                            }
                        }else{
                            $cartData = array('discount' => -round($discount),'total'=> round($quote->getSubtotal()) - round($discount),'subtotal'=> round($quote->getSubtotal()), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => $bankDetailsColl[0]['bank_name']);
                            $detials = array('success' => true,'is_BIN_promo_applied' => true,'data'=>$cartData);
                                    echo  json_encode($detials);exit;
                        }
                    }
                }else{
                    $this->logger->info('Bin coupon is not exists');
                    $cartData = array('discount' => 0,'total'=> $quote->getSubtotal(),'subtotal'=> $quote->getSubtotal(), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => '');
                    $detials = array('success' => false,'is_BIN_promo_applied' => false,'error'=>'Bin Number '. $requestDataObj->bin_number .' is not for the country '. $currentCountry,'data'=>$cartData);
                    echo  json_encode($detials);exit;
                }
                }else{
                    $this->logger->info('Bin Number:: '. $requestDataObj->bin_number .' is not for the country '. $currentCountry);
                    $cartData = array('discount' => 0,'total'=> $quote->getSubtotal(),'subtotal'=> $quote->getSubtotal(), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => '');
                    $detials = array('success' => false,'is_BIN_promo_applied' => false,'error'=>'Bin Number '. $requestDataObj->bin_number .' is not for the country '. $currentCountry,'data'=>$cartData);
                    echo  json_encode($detials);exit;
                }
            }else{
                $cartData = array('discount' => 0,'total'=> 0,'subtotal'=> 0, 'currency_code'=> '','bank_name' => '');
                $detials = array('success' => false,'error'=>'The quote not exists','data'=>$cartData);
                echo  json_encode($detials);exit;
            }
        }else{
            $this->logger->info('Bin Number:: '. $requestDataObj->bin_number .' not found in system');
            $cartData = array('discount' => 0,'total'=> $quote->getSubtotal(),'subtotal'=> $quote->getSubtotal(), 'currency_code'=> $quote->getBaseCurrencyCode(),'bank_name' => '');
            $detials = array('success' => false,'is_BIN_promo_applied' => false, 'error'=>'Bin Number:: '. $requestDataObj->bin_number .' not found in system','data'=>$cartData);
                echo  json_encode($detials);exit;
        }
        exit;
        $this->logger->info('Info API execution END');
    }
}