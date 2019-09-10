<?php
namespace Ambab\BankDiscount\Helper;

use Ambab\BankDiscount\Helper\PayPal\Api\Amount;
use Ambab\BankDiscount\Helper\PayPal\Api\Details;
use Ambab\BankDiscount\Helper\PayPal\Api\Item;
use Ambab\BankDiscount\Helper\PayPal\Api\ItemList;
use Ambab\BankDiscount\Helper\PayPal\Api\Payer;
use Ambab\BankDiscount\Helper\PayPal\Api\Payment;
use Ambab\BankDiscount\Helper\PayPal\Api\RedirectUrls;
use Ambab\BankDiscount\Helper\PayPal\Api\Transaction;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @param $itemId
     */
    public function paypaldata($requestDataObj, $quote)
    {
        $apiContext = new PayPal\Rest\ApiContext(
            new PayPal\Auth\OAuthTokenCredential(
                'AShA9xTeQ79QSCaMfMBPxkA05_hF5gTVZHXbJhKjtA4dr-6plH1km6IFg2TDKVnGfumRZhGdvVAR0cQb', //YOUR APPLICATION CLIENT ID
                'EJ6S2K09oy6opUqbjqWvRrR1IW29OY2SPTwaU5TUpVuILXxeLfdUPkrSBj-3_1n2q-pk4gzFTMM4rf_5' //YOUR APPLICATION CLIENT SECRET
            )
        );

        $returnURL = 'http://local.6stage.com/rest/V1/paypal/process.php';
        $cancelURL = 'http://local.6stage.com/rest/V1/paypal/cancel.php';

        // Create new payer and method
        $payer = new Payer();
        $payer->setPaymentMethod($requestDataObj->payment_method_code);

        $quoteItems = $quote->getAllVisibleItems();
        //$items = $quote->getAllItems();
        $cartItems = array();
        $i = 0;
        foreach($quoteItems as $item) {
            $cartItems[$i]['name'] = $item->getName(); 
            $cartItems[$i]['currency'] = 'USD'; 
            $cartItems[$i]['quantity'] = number_format($item->getQty()); 
            $cartItems[$i]['sku'] = $item->getSku(); 
            $cartItems[$i]['price'] = number_format($item->getPrice(), 2); 
            $i++;      
        }

        $itemList = new ItemList();
        $itemList->setItems($cartItems);

        // TODO: you can set here payment details
        $details = new Details();
        $details->setShipping(0) // optional
                ->setSubtotal($quote->getSubtotal());

        // Set payment amount
        $amount = new Amount();
        $amount->setCurrency("USD")
               ->setTotal($quote->getGrandTotal())
               ->setDetails($details);

        // Set transaction object
        $transaction = new Transaction();
        $transaction->setAmount($amount)
                    ->setItemList($itemList)
                    ->setDescription("Payment description")                                                                              
                    ->setInvoiceNumber(uniqid());
  
        // Set redirect URLs
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($returnURL)
                     ->setCancelUrl($cancelURL);

        // Create the full payment object
        $payment = new Payment();
        $payment->setIntent('sale')
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions(array($transaction));
                
        // Create payment with valid API context
        try 
        {
            $payment->create($apiContext);
            // Get PayPal redirect URL and redirect the customer
            $approvalUrl = $payment->getApprovalLink();
            $response = [
                'redirect_url' => $approvalUrl,
                'payment_id' => $payment->id
            ];

           echo "<pre>";
        print_r($response);die;
            return json_encode($response);
        } catch (PayPal\Exception\PayPalConnectionException $ex) {
            echo $ex->getCode();
            echo $ex->getData();
            die($ex);
        } catch (Exception $ex) {
            die($ex);
        }
    }
}
