<?php

namespace Safecharge\Safecharge\Model\Redirect;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Quote\Model\Quote;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Payment;
use Magento\Framework\App\Request\Http as Http;

/**
 * Safecharge Safecharge config provider model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Url
{
    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    
    private $request;

    /**
     * Url constructor.
     *
     * @param ModuleConfig    $moduleConfig
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        ModuleConfig $moduleConfig,
        CheckoutSession $checkoutSession,
        Http $request
    ) {
        $this->moduleConfig     = $moduleConfig;
        $this->checkoutSession  = $checkoutSession;
        $this->request          = $request;
    }
	
    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->moduleConfig->getEndpoint() . '?' . http_build_query($this->prepareParams());
    }

    /**
     * @return string
     */
    public function getPostData()
    {
        // in case we use WebSDK just go to Success page
        if($this->request->getParam('method') === 'cc_card' && $this->request->getParam('transactionId')) {
            $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
            $storeManager   = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
            
            $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
            $quote_id = $cart->getQuote()->getId();
            
            return [
				'url' => $this->moduleConfig->getCallbackCompleteUrl()
            ];
        }
        
        return [
            "url" => $this->moduleConfig->getEndpoint(),
            "params" => $this->prepareParams()
        ];
    }

    /**
     * @return array
     */
    protected function prepareParams()
    {
		$this->moduleConfig->createLog('URL.php prepareParams()');
		return '';
		
		// TODO - do we use this
//        if ($this->moduleConfig->getPaymentSolution() === Payment::SOLUTION_INTERNAL) {
//            return '';
//        }

        /** @var Quote $quote */
        $quote = $this->checkoutSession->getQuote();

        $quotePayment = $quote->getPayment();

        $shipping = 0;
        $totalTax = 0;
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress !== null) {
            $shipping = $shippingAddress->getBaseShippingAmount();
            $totalTax = $shippingAddress->getBaseTaxAmount();
        }

        $reservedOrderId = $quotePayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID) ?: $this->moduleConfig->getReservedOrderId();

        $queryParams = [
            'merchant_id'			=> $this->moduleConfig->getMerchantId(),
            'merchant_site_id'		=> $this->moduleConfig->getMerchantSiteId(),
            'customField1'			=> $this->moduleConfig->getSourcePlatformField(),
            'total_amount'			=> number_format((float)$quote->getBaseGrandTotal(), 2, '.', ''),
            'discount'				=> 0,
            'shipping'				=> 0,
            'total_tax'				=> 0,
            'currency'				=> $quote->getBaseCurrencyCode(),
            'user_token_id'			=> $quote->getCustomerId(),
            'time_stamp'			=> date('YmdHis'),
            'version'				=> '4.0.0',
            'success_url'			=> $this->moduleConfig->getCallbackCompleteUrl(),
            'pending_url'			=> $this->moduleConfig->getCallbackCompleteUrl(),
            'error_url'				=> $this->moduleConfig->getCallbackCompleteUrl(),
            'back_url'				=> $this->moduleConfig->getBackUrl(),
            'notify_url'			=> $this->moduleConfig->getCallbackDmnUrl($reservedOrderId),
            'merchant_unique_id'	=> $reservedOrderId,
            'ipAddress'				=> $quote->getRemoteIp(),
            'encoding'				=> 'UTF-8',
        ];

        if (($billing = $quote->getBillingAddress()) && $billing !== null) {
            $billingAddress = [
                'first_name' => $billing->getFirstname(),
                'last_name' => $billing->getLastname(),
                'address' => is_array($billing->getStreet()) ? implode(' ', $billing->getStreet()) : '',
                'cell' => '',
                'phone' => $billing->getTelephone(),
                'zip' => $billing->getPostcode(),
                'city' => $billing->getCity(),
                'country' => $billing->getCountryId(),
                'state' => $billing->getRegionCode(),
                'email' => $billing->getEmail(),
            ];
            $queryParams = array_merge($queryParams, $billingAddress);
        }

        $queryParams['item_name_1'] = 'product1';
        $queryParams['item_amount_1'] = $queryParams['total_amount'];
        $queryParams['item_quantity_1'] = 1;
        $queryParams['numberofitems'] = 1;

        $queryParams['checksum'] = hash(
            $this->moduleConfig->getHash(),
            utf8_encode($this->moduleConfig->getMerchantSecretKey() . implode("", $queryParams))
        );

        return $queryParams;
    }
}
