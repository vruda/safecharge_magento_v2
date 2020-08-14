<?php

namespace Safecharge\Safecharge\Model\Request;

use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\RequestInterface;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;
use Magento\Framework\Exception\PaymentException;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;

/**
 * Safecharge Safecharge open order request model.
 */
class OpenOrder extends AbstractRequest implements RequestInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var array
     */
    protected $orderData;
    
    protected $cart;
    
    private $billingAddress; // array
    private $countryCode; // string

    /**
     * OpenOrder constructor.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     * @param Factory          $requestFactory
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        \Magento\Checkout\Model\Cart $cart
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory	= $requestFactory;
        $this->cart             = $cart;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::OPEN_ORDER_METHOD;
    }

    /**
     * @param array $orderData
     *
     * @return OpenOrder
     */
    public function setOrderData(array $orderData)
    {
        $this->orderData = $orderData;
        return $this;
    }
    
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
        $this->sendRequest();

        return $this
            ->getResponseHandler()
            ->process();
    }
    
    public function setBillingAddress($data = [])
    {
        $this->billingAddress = $data;
    }
    
    public function setCountryCode($data = '')
    {
        $this->countryCode = $data;
    }


    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::OPEN_ORDER_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     */
    protected function getParams()
    {
        if (null === $this->cart || empty($this->cart)) {
            throw new PaymentException(__('There is no Cart data.'));
        }
        
        $billing_country = $this->cart->getQuote()->getBillingAddress()->getCountry();
        if (empty($billing_country)) {
            $billing_country = $this->config->getQuoteCountryCode();
        }
        if (empty($billing_country)) {
            $billing_country = $this->config->getDefaultCountry();
        }
        if (!empty($this->billingAddress) && !empty($this->countryCode)) {
            $billing_country = $this->countryCode;
        }
        
        $email = $this->cart->getQuote()->getBillingAddress()->getEmail();
        if (empty($email)) {
            $email = $this->cart->getQuote()->getCustomerEmail();
        }
        if (empty($email)) {
            $email = $this->config->getCheckoutSession()->getQuote()->getCustomerEmail();
        }
        if (empty($email) && !empty($_COOKIE['guestSippingMail'])) {
            $email = filter_var($_COOKIE['guestSippingMail'], FILTER_VALIDATE_EMAIL);
        }
        if (empty($email)) {
            $email = 'quoteID_' . $this->config->getCheckoutSession()->getQuoteId() . '@magentoMerchant.com';
        }
        
        $shipping_email = $this->cart->getQuote()->getShippingAddress()->getEmail();
        if (empty($shipping_email)) {
            $shipping_email = $email;
        }
        
        $b_f_name = $this->cart->getQuote()->getBillingAddress()->getFirstname();
        if (empty($b_f_name)) {
            $b_f_name = $this->cart->getQuote()->getCustomerFirstname();
        }
        if (!empty($this->billingAddress)) {
            $b_f_name = $this->billingAddress['firstName'];
        }
        
        $b_l_name = $this->cart->getQuote()->getBillingAddress()->getLastname();
        if (empty($b_l_name)) {
            $b_l_name = $this->cart->getQuote()->getCustomerLastname();
        }
        if (!empty($this->billingAddress)) {
            $b_l_name = $this->billingAddress['lastName'];
        }
        
        $billing_address = $this->cart->getQuote()->getBillingAddress()->getStreetFull();
        if (!empty($this->billingAddress)) {
            $billing_address = $this->billingAddress['address'];
        }
        
        $billing_phone = $this->cart->getQuote()->getBillingAddress()->getTelephone();
        if (!empty($this->billingAddress)) {
            $billing_phone = $this->billingAddress['phone'];
        }
        
        $billing_zip = $this->cart->getQuote()->getBillingAddress()->getPostcode();
        if (!empty($this->billingAddress)) {
            $billing_zip = $this->billingAddress['zip'];
        }
        
        $billing_city = $this->cart->getQuote()->getBillingAddress()->getCity();
        if (!empty($this->billingAddress)) {
            $billing_city = $this->billingAddress['city'];
        }
		
		// check in the cart for Nuvei Payment Plan
		$items		= $this->cart->getQuote()->getItems();
		$plans_ids	= [];
		
		foreach($items as $item) {
			$product = $item->getProduct();
			
			$plan_id = $product->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME);
//			$plan_id2 = $item->getProduct()->getCustomAttribute(\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME);
//			$plan_id3 = $item->getProduct()->getAttributeText(\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME);
//			$plan_id5 = $item->getProduct()->getNuveiPaymentPlans();
			
			
			$this->config->createLog($plan_id, '$plan_id');
//			$this->config->createLog($plan_id2, '$plan_id2');
//			$this->config->createLog($plan_id3, '$plan_id3');
//			$this->config->createLog($plan_id5, '$plan_id5');
			
			if(empty($plan_id) || intval($plan_id) == 1) {
				$options = $product->getTypeInstance(true)->getOrderOptions($product);
				
				$this->config->createLog($options, '$options');
				
				if(!empty($options['attributes_info']) && is_array($options['attributes_info'])) {
					foreach($options['attributes_info'] as $data) {
						if(
							!empty($data['label'])
							&& $data['label'] == \Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_LABEL
							&& !empty($data['option_value'])
						) {
							$plan_id = $data['option_value'];
						}
					}
				}
			}
			
			if(is_numeric($plan_id) && intval($plan_id) > 1) {
				$plans_ids[] = $plan_id;
			}
		}
		
		$this->config->createLog($plans_ids, '$plans_ids');

		$this->config->setNuveiUseCcOnly(!empty($plans_ids) ? true : false);
		// check in the cart for Nuvei Payment Plan END
        
        $params = array_merge_recursive(
            [
                'userTokenId'        => $email,
                'clientUniqueId'    => $this->config->getCheckoutSession()->getQuoteId(),
                
                'currency'          => empty($this->cart->getQuote()->getOrderCurrencyCode())
                    ? $this->cart->getQuote()->getStoreCurrencyCode() : $this->cart->getQuote()->getOrderCurrencyCode(),
                
                'amount'            => (string) number_format($this->cart->getQuote()->getGrandTotal(), 2, '.', ''),
                'deviceDetails'     => $this->config->getDeviceDetails(),
                
                'shippingAddress'    => [
                    "firstName"    => $this->cart->getQuote()->getShippingAddress()->getFirstname(),
                    "lastName"    => $this->cart->getQuote()->getShippingAddress()->getLastname(),
                    "address"    => $this->cart->getQuote()->getShippingAddress()->getStreetFull(),
                    "phone"        => $this->cart->getQuote()->getShippingAddress()->getTelephone(),
                    "zip"        => $this->cart->getQuote()->getShippingAddress()->getPostcode(),
                    "city"        => $this->cart->getQuote()->getShippingAddress()->getCity(),
                    'country'   => $this->cart->getQuote()->getShippingAddress()->getCountry(),
                    'email'        => $shipping_email,
                ],
                
                'billingAddress'    => [
                    "firstName"    => $b_f_name,
                    "lastName"    => $b_l_name,
                    "address"    => $billing_address,
                    "phone"        => $billing_phone,
                    "zip"        => $billing_zip,
                    "city"        => $billing_city,
                    'country'    => $billing_country,
                    'email'     => $email,
                ],
                
                'urlDetails'        => [
                    'successUrl'        => $this->config->getCallbackSuccessUrl(),
                    'failureUrl'        => $this->config->getCallbackErrorUrl(),
                    'pendingUrl'        => $this->config->getCallbackPendingUrl(),
                    'backUrl'           => $this->config->getBackUrl(),
                    'notificationUrl'    => $this->config->getCallbackDmnUrl(),
                ],
                
                'paymentOption'        => ['card' => ['threeD' => ['isDynamic3D' => 1]]],
                'transactionType'    => $this->config->getPaymentAction(),
                
                'merchantDetails'    => [
                    'customField1' => (string) number_format($this->cart->getQuote()->getGrandTotal(), 2, '.', ''), // pass amount
                    'customField2' => json_encode($plans_ids), // payment plans IDs
                ],
            ],
            parent::getParams()
        );
        
        $params['userDetails'] = $params['billingAddress'];
        
        return $params;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'clientRequestId',
            'amount',
            'currency',
            'timeStamp',
        ];
    }
}
