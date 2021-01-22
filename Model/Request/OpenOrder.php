<?php

namespace Nuvei\Payments\Model\Request;

use Nuvei\Payments\Lib\Http\Client\Curl;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Config;
use Nuvei\Payments\Model\RequestInterface;
use Nuvei\Payments\Model\Response\Factory as ResponseFactory;
use Magento\Framework\Exception\PaymentException;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;
use Nuvei\Payments\Model\Payment;

/**
 * Nuvei Payments open order request model.
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
	private $requestParams = [];

    /**
     * OpenOrder constructor.
     *
     * @param Logger $logger
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     * @param Factory          $requestFactory
     */
    public function __construct(
        \Nuvei\Payments\Model\Logger $logger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        \Magento\Checkout\Model\Cart $cart
    ) {
        parent::__construct(
            $logger,
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
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
		// first try to update order
		$quote		= $this->cart->getQuote();
		$order_data	= $quote->getPayment()->getAdditionalInformation(Payment::ORDER_DATA);
		
		// first try - update order
		if(!empty($order_data)) {
			$update_order_request = $this->requestFactory->create(AbstractRequest::UPDATE_ORDER_METHOD);

			$req_resp = $update_order_request
				->setOrderData($order_data)
				->setBillingAddress($this->billingAddress)
				->process();
		}
		
		// if UpdateOrder fails - continue with OpenOrder
		if(empty($req_resp['status']) || 'success' != strtolower($req_resp['status'])) {
			$req_resp = $this->sendRequest(true);
		}
		
		$this->orderId      = $req_resp['orderId'];
        $this->sessionToken = $req_resp['sessionToken'];
        $this->ooAmount     = $req_resp['merchantDetails']['customField1'];

		// save the session token in the Quote
		$quote->getPayment()->setAdditionalInformation(
            Payment::ORDER_DATA,
            [
				'sessionToken'		=> $req_resp['sessionToken'],
			//	'amount'			=> $this->requestParams['amount'],
//				'userTokenId'		=> $req_resp['userTokenId'],
				'clientRequestId'	=> $req_resp['clientRequestId'],
				'orderId'			=> $req_resp['orderId'],
			//	'merchantDetails'	=> $req_resp['merchantDetails'],
			//	'billingAddress'	=> $this->requestParams['billingAddress'],
			//	'items'				=> '', // TODO
			]
        );
		$this->cart->getQuote()->save();
		
        return $this;
    }
	
	public function setBillingAddress($billingAddress)
	{
		$this->billingAddress = $billingAddress;
		
		return $this;
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
		
		$quote		= $this->cart->getQuote();
		$items		= $quote->getItems();
		$items_data	= [];
		
		// check in the cart for Nuvei Payment Plan
		$subs_data	= [];
		
		// iterate over Items and search for Subscriptions
		if(is_array($items)) {
			foreach($items as $item) {
				$items_data[$item->getId()] = [
					'quantity'	=> $item->getQty(),
					'price'		=> $item->getPrice(),
				];

				/*
				$product	= $item->getProduct();
				$attributes	= $product->getAttributes();

				// if subscription is not enabled continue witht the next product
				if($item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE) != 1) {
					continue;
				}

				// mandatory data
				$subs_data[$product->getId()] = array(
					'planId' => $item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_PLANS_ATTR_NAME),

					'initialAmount' => number_format($item->getProduct()
						->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT), 2, '.', ''),

					'recurringAmount' => number_format($item->getProduct()
						->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_REC_AMOUNT), 2, '.', ''),
				);

				$this->config->createLog($subs_data, '$subs_data');

				# optional data
				$recurr_unit = $item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_UNITS);
				$recurr_period = $item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_PERIOD);
				$subs_data[$product->getId()]['recurringPeriod'][strtolower($recurr_unit)] = $recurr_period;

				$trial_unit = $item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_UNITS);
				$trial_period = $item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_PERIOD);
				$subs_data[$product->getId()]['startAfter'][strtolower($trial_unit)] = $trial_period;

				$end_after_unit = $item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_UNITS);
				$end_after_period = $item->getProduct()->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_PERIOD);
				$subs_data[$product->getId()]['endAfter'][strtolower($end_after_unit)] = $end_after_period;
				# optional data END
				 */
			}
		}
		
		$this->config->setNuveiUseCcOnly(!empty($subs_data) ? true : false);
		
		$billing_address = $this->config->getQuoteBillingAddress();
		if(!empty($this->billingAddress)) {
			$billing_address['firstName']	= $this->billingAddress['firstname'] ?: $billing_address['firstName'];
			$billing_address['lastName']	= $this->billingAddress['lastname'] ?: $billing_address['lastName'];
			
			if(is_array($this->billingAddress['street']) && !empty($this->billingAddress['street'])) {
				$billing_address['address'] = implode(' ', $this->billingAddress['street']);
			}
			
			$billing_address['phone']	= $this->billingAddress['telephone'] ?: $billing_address['phone'];
			$billing_address['zip']		= $this->billingAddress['postcode'] ?: $billing_address['zip'];
			$billing_address['city']	= $this->billingAddress['city'] ?: $billing_address['city'];
			$billing_address['country']	= $this->billingAddress['countryId'] ?: $billing_address['country'];
		}
        
		$currency = $quote->getOrderCurrencyCode();
		if(empty($currency)) {
			$currency = $quote->getStoreCurrencyCode();
		}
		if(empty($currency)) {
			$currency = $this->config->getQuoteBaseCurrency();
		}
		
        $this->requestParams = array_merge_recursive(
            [
                'clientUniqueId'    => $this->config->getCheckoutSession()->getQuoteId(),
                'currency'          => $currency,
                'amount'            => (string) number_format($quote->getGrandTotal(), 2, '.', ''),
                'deviceDetails'     => $this->config->getDeviceDetails(),
                'shippingAddress'   => $this->config->getQuoteShippingAddress(),
                'billingAddress'    => $billing_address,
                
                'urlDetails'        => [
                    'successUrl'        => $this->config->getCallbackSuccessUrl(),
                    'failureUrl'        => $this->config->getCallbackErrorUrl(),
                    'pendingUrl'        => $this->config->getCallbackPendingUrl(),
                    'backUrl'           => $this->config->getBackUrl(),
                    'notificationUrl'   => $this->config->getCallbackDmnUrl(),
                ],
                
                'paymentOption'      => ['card' => ['threeD' => ['isDynamic3D' => 1]]],
                'transactionType'    => $this->config->getPaymentAction(),
                
                'merchantDetails'    => [
                    'customField1' => (string) number_format($quote->getGrandTotal(), 2, '.', ''), // pass amount
                    'customField2' => json_encode($subs_data), // subscription data
				//	'customField3' => 'Magento v.' . $this->config->getMagentoVersion(), // pass it in AbstractRequest
					'customField4' => time(), // time when we create the request
					'customField5' => json_encode($items_data), // list of Order items
                ],
            ],
            parent::getParams()
        );
        
        $this->requestParams['userDetails'] = $this->requestParams['billingAddress'];
        
        return $this->requestParams;
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
