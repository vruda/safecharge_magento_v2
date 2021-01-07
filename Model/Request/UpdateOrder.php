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
class UpdateOrder extends AbstractRequest implements RequestInterface
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
    
    /**
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
        return self::UPDATE_ORDER_METHOD;
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
		$req_resp = $this->sendRequest(true, true);
		
//		$this->orderId      = $req_resp['orderId'];
//        $this->sessionToken = $req_resp['sessionToken'];
//        $this->ooAmount     = $req_resp['merchantDetails']['customField1'];

		// save the session token in the Quote
//		$this->cart->getQuote()->getPayment()->unsAdditionalInformation('nuvei_session_token');
//		$this->cart->getQuote()->getPayment()->setAdditionalInformation(
//            'nuvei_session_token',
//            $req_resp['sessionToken']
//        );
//		$this->cart->getQuote()->save();
		
        return $req_resp;
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
			$this->config->createLog('UpdateOrder Error - There is no Cart data.');
			
            throw new PaymentException(__('There is no Cart data.'));
        }
        
		// check in the cart for Nuvei Payment Plan
		$quote		= $this->cart->getQuote();
		$items		= $this->cart->getQuote()->getItems();
		$items_data	= [];
		$subs_data	= [];
		
		// iterate over Items and search for Subscriptions
		foreach($items as $item) {
			$product = $item->getProduct();
			
			$items_data[$item->getId()] = [
				'quantity'	=> $item->getQty(),
				'price'		=> $item->getPrice(),
			];
			
			/*
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
			 */
		}
		
		$this->config->setNuveiUseCcOnly(!empty($subs_data) ? true : false);
		
		$currency = $quote->getOrderCurrencyCode();
		if(empty($currency)) {
			$currency = $quote->getStoreCurrencyCode();
		}
		if(empty($currency)) {
			$currency = $this->config->getQuoteBaseCurrency();
		}
		
		$params = array_merge_recursive(
			parent::getParams(),
			[
				'currency'			=> $currency,
				'amount'			=> (string) number_format($quote->getGrandTotal(), 2, '.', ''),
				'billingAddress'	=> $this->config->getQuoteBillingAddress(),
				'shippingAddress'   => $this->config->getQuoteShippingAddress(),
				
				'items'				=> [[
					'name'		=> 'magento_order',
					'price'		=> (string) number_format($quote->getGrandTotal(), 2, '.', ''),
					'quantity'	=> 1,
				]],
				'merchantDetails'	=> [
					'customField1'		=> (string) number_format($quote->getGrandTotal(), 2, '.', ''), // pass amount
					'customField2'		=> json_encode($subs_data), // subscription data
				//	'customField3'		=> 'Magento v.' . $this->config->getMagentoVersion(), // pass it in AbstractRequest
					'customField4'		=> time(), // time when we create the request
					'customField5'		=> json_encode($items_data), // list of Order items
				],
			]
		);
		
		$params['userDetails']		= $params['billingAddress'];
		$params['sessionToken']		= $this->orderData['sessionToken'];
		$params['orderId']			= $this->orderData['orderId'] ?: '';
		$params['userTokenId']		= $this->orderData['userTokenId'] ?: '';
		$params['clientRequestId']	= $this->orderData['clientRequestId'] ?: '';
		
		$params['checksum'] = hash(
			$this->config->getHash(),
			$this->config->getMerchantId() . $this->config->getMerchantSiteId() . $params['clientRequestId']
				. $params['amount'] . $params['currency'] . $params['timeStamp'] . $this->config->getMerchantSecretKey()
		);
		
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
