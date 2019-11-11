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
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
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
        RequestFactory $requestFactory
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory = $requestFactory;
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
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::OPEN_ORDER_HANDLER;
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
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     */
    protected function getParams()
    {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$cart = $objectManager->get('\Magento\Checkout\Model\Cart');
		
        if (null === $cart || empty($cart)) {
            throw new PaymentException(__('There is no Cart data.'));
        }
		
		$billingAddress = $cart->getQuote()->getBillingAddress()->getData();

        $params = array_merge_recursive(
			[
				'amount'            => (string) round($cart->getQuote()->getGrandTotal(), 2),
				'currency'          => empty($cart->getQuote()->getOrderCurrencyCode())
					? $cart->getQuote()->getStoreCurrencyCode() : $cart->getQuote()->getOrderCurrencyCode(),
				'urlDetails'        => array(
					'successUrl'        => $this->config->getCallbackSuccessUrl(),
					'failureUrl'        => $this->config->getCallbackErrorUrl(),
					'pendingUrl'        => $this->config->getCallbackPendingUrl(),
					'backUrl'			=> $this->config->getBackUrl(),
					'notificationUrl'   => $this->config->getCallbackDmnUrl(),
				),
				'deviceDetails'     => $this->config->getDeviceDetails(),
				'userTokenId'       => $cart->getQuote()->getCustomerEmail(),
				'billingAddress'    => array(
					'country' => $billingAddress['country_id'],
				),
				'paymentOption'		=> ['card' => ['threeD' => ['isDynamic3D' => 1]]],
			],
            parent::getParams()
        );
		
		$this->config->createLog('Open Order.');

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
