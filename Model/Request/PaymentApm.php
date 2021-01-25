<?php

namespace Nuvei\Payments\Model\Request;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Nuvei\Payments\Lib\Http\Client\Curl;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Config;
use Nuvei\Payments\Model\Payment;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;
use Nuvei\Payments\Model\RequestInterface;
use Nuvei\Payments\Model\Response\Factory as ResponseFactory;

/**
 * Nuvei Payments paymentAPM request model.
 */
class PaymentApm extends AbstractRequest implements RequestInterface
{

    /**
     * @var string|null
     */
    protected $paymentMethod;
    
    /**
     * @var array|null
     */
    protected $paymentMethodFields;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     * @param Factory          $requestFactory
     * @param CheckoutSession  $checkoutSession
     */
    public function __construct(
        \Nuvei\Payments\Model\Logger $logger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct(
            $logger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory    = $requestFactory;
        $this->checkoutSession    = $checkoutSession;
    }

    /**
     * @param string $paymentMethod
     * @return $this
     */
    public function setPaymentMethod($paymentMethod)
    {
        $this->paymentMethod = trim((string)$paymentMethod);
        return $this;
    }
    
    /**
     * @param array $paymentMethodFields
     * @return $this
     */
    public function setPaymentMethodFields($paymentMethodFields)
    {
        $this->paymentMethodFields = $paymentMethodFields;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }
    
    /**
     * @return string
     */
    public function getPaymentMethodFields()
    {
        return $this->paymentMethodFields;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::PAYMENT_APM_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_APM_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        $quote			= $this->checkoutSession->getQuote();
        $quotePayment	= $quote->getPayment();

//        $this->config->createLog('requestFactory GET_SESSION_TOKEN_METHOD - PaymentApm.php');
        
//        $tokenRequest = $this->requestFactory
//            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
//        $tokenResponse = $tokenRequest->process();

//        $quotePayment->unsAdditionalInformation(Payment::TRANSACTION_SESSION_TOKEN);
//        $quotePayment->setAdditionalInformation(
//            Payment::TRANSACTION_SESSION_TOKEN,
//            $tokenResponse->getToken()
//        );

		$this->config->createLog(
			$quotePayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID),
			'PaymentAPM TRANSACTION_ORDER_ID'
		);
		
        $reservedOrderId = $quotePayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID)
            ?: $this->config->getReservedOrderId();
		
		$order_data = $quotePayment->getAdditionalInformation(Payment::CREATE_ORDER_DATA);
		
		if (empty($order_data) || empty($order_data['sessionToken'])) {
			$msg = 'PaymentApm Error - missing Session Token.';
			
			$this->config->createLog($order_data, $msg);
			
            throw new PaymentException(__($msg));
        }
		
		$this->config->createLog($order_data, 'PaymentAPM $order_data');
		$this->config->createLog($_POST, 'PaymentAPM $_POST');
		
//		$session_token = $order_data['sessionToken'] ?: $tokenResponse->getToken();
		
//		if(empty($order_data['sessionToken'])) {
//			$session_token = $tokenResponse->getToken();
//		}
//		else {
//			$session_token = $order_data['sessionToken'];
//		}
		
        $params = array_merge_recursive(
            $this->getQuoteData($quote),
            [
                'sessionToken'          => $order_data['sessionToken'],
                'amount'                => (float)$quote->getGrandTotal(),
//                'merchant_unique_id'    => $reservedOrderId,
//				'clientUniqueId'		=> $this->config->setClientUniqueId($reservedOrderId),
                
				'urlDetails'            => [
                    'successUrl'        => $this->config->getCallbackSuccessUrl(),
                    'failureUrl'        => $this->config->getCallbackErrorUrl(),
                    'pendingUrl'        => $this->config->getCallbackPendingUrl(),
                    'backUrl'            => $this->config->getBackUrl(),
                    'notificationUrl'    => $this->config->getCallbackDmnUrl($reservedOrderId),
                ],
                
				'paymentMethod'            => $this->getPaymentMethod(),
            ]
        );
		
		$params['clientUniqueId'] = $this->config->setClientUniqueId($reservedOrderId);
		
        $pmFields = $this->getPaymentMethodFields();
        
        if (null !== $pmFields) {
            $params['userAccountDetails'] = $pmFields;
        }

        $params = array_merge_recursive($params, parent::getParams());

//        $this->logger->updateRequest(
//            $this->getRequestId(),
//            [
//                'parent_request_id' => $quotePayment->getAdditionalInformation(Payment::TRANSACTION_REQUEST_ID),
//                'increment_id' => $this->config->getReservedOrderId(),
//            ]
//        );

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
