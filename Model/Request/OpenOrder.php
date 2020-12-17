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
		$req_resp = $this->sendRequest(true);
		
		$this->orderId      = $req_resp['orderId'];
        $this->sessionToken = $req_resp['sessionToken'];
        $this->ooAmount     = $req_resp['merchantDetails']['customField1'];

        return $this;
		
//        return $this
//            ->getResponseHandler()
//            ->process();
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
		$subs_data	= [];
		
		// iterate over Items and search for Subscriptions
		/*
		foreach($items as $item) {
			$product	= $item->getProduct();
			$attributes	= $product->getAttributes();
			
			// if subscription is not enabled continue witht the next product
			if($item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_ENABLE) != 1) {
				continue;
			}
			
			// mandatory data
			$subs_data[$product->getId()] = array(
				'planId' => $item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME),
				
				'initialAmount' => number_format($item->getProduct()
					->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT), 2, '.', ''),
				
				'recurringAmount' => number_format($item->getProduct()
					->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_AMOUNT), 2, '.', ''),
			);
			
			$this->config->createLog($subs_data, '$subs_data');
			
			# optional data
			$recurr_unit = $item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_RECURR_UNITS);
			$recurr_period = $item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_RECURR_PERIOD);
			$subs_data[$product->getId()]['recurringPeriod'][strtolower($recurr_unit)] = $recurr_period;
			
			$trial_unit = $item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_TRIAL_UNITS);
			$trial_period = $item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_TRIAL_PERIOD);
			$subs_data[$product->getId()]['startAfter'][strtolower($trial_unit)] = $trial_period;
			
			$end_after_unit = $item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_AFTER_UNITS);
			$end_after_period = $item->getProduct()->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_AFTER_PERIOD);
			$subs_data[$product->getId()]['endAfter'][strtolower($end_after_unit)] = $end_after_period;
			# optional data END
		}
		 */	
		
		$this->config->setNuveiUseCcOnly(!empty($subs_data) ? true : false);
        
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
                    'customField2' => json_encode($subs_data), // subscription data
                    // customField3 => Magento version is added in AbstractRequest
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
	
	/**
     * Get attribute options
     *
     * @param \Magento\Eav\Api\Data\AttributeInterface $attribute
     * @return array
     */
    private function getOptions(\Magento\Eav\Api\Data\AttributeInterface $attribute) : array
    {
        $return = [];

        $options = $attribute->getOptions();
        foreach ($options as $option) {
            if ($option->getValue()) {
                $return[] = [
                    'value' => $option->getLabel(),
                    'label' => $option->getLabel(),
                    'parentAttributeLabel' => $attribute->getDefaultFrontendLabel()
                ];
            }
        }

        return $return;
    }
}
