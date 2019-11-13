<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\AbstractPayment;
use Safecharge\Safecharge\Model\Request\Factory as PaymentFactory;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Safecharge\Safecharge\Model\RequestInterface;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;
use Safecharge\Safecharge\Model\Service\CardTokenization as CardTokenizationService;

/**
 * Safecharge Safecharge cc payment request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Cc extends AbstractPayment implements RequestInterface
{
    /**
     * @var PaymentTokenManagementInterface
     */
    protected $paymentTokenManagement;

    /**
     * @var CardTokenizationService
     */
    protected $cardTokenizationService;

    /**
     * Cc constructor.
     *
     * @param SafechargeLogger                $safechargeLogger
     * @param Config                          $config
     * @param Curl                            $curl
     * @param PaymentFactory                  $requestFactory
     * @param Factory                         $paymentRequestFactory
     * @param ResponseFactory                 $responseFactory
     * @param OrderPayment|null               $orderPayment
     * @param float|null                      $amount
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param CardTokenizationService         $cardTokenizationService
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        PaymentFactory $requestFactory,
        PaymentRequestFactory $paymentRequestFactory,
        ResponseFactory $responseFactory,
        $orderPayment,
        $amount,
        PaymentTokenManagementInterface $paymentTokenManagement,
        CardTokenizationService $cardTokenizationService
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $requestFactory,
            $paymentRequestFactory,
            $responseFactory,
            $orderPayment,
            $amount
        );

        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->cardTokenizationService = $cardTokenizationService;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_CC_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_CC_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $this->orderPayment;

        /** @var Order $order */
        $order = $orderPayment->getOrder();

        $this->processCardTokenization();

        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
        $tokenResponse = $tokenRequest->process();

        $params = array_merge_recursive(
            $this->getOrderData($order),
            [
                'sessionToken' => $tokenResponse->getToken(),
                'transactionType' => $this->getActionType(),
                'isRebilling' => 0,
                'amount' => (float)$order->getGrandTotal(),
                'merchant_unique_id' => $order->getIncrementId(),
                'urlDetails' => [
                    'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
                ],
            ]
        );

        // Add card details.
        $ccToken = $orderPayment->getAdditionalInformation(Payment::KEY_CC_TOKEN);
        if ($ccToken === null) {
            $params['cardData'] = [
                'cardNumber' => $orderPayment->getCcNumber(),
                'cardHolderName' => $orderPayment->getCcOwner(),
                'expirationMonth' => $orderPayment->getCcExpMonth(),
                'expirationYear' => $orderPayment->getCcExpYear(),
                'CVV' => $orderPayment->getCcCid(),
            ];
        } else {
            $paymentToken = $this->paymentTokenManagement->getByPublicHash(
                $ccToken,
                $order->getCustomerId()
            );

            if ($paymentToken === null) {
                throw new PaymentException(
                    __('Requested payment token does not exists.')
                );
            }

            $paymentTokenDetails = $paymentToken->getTokenDetails();
            $paymentTokenDetails = json_decode($paymentTokenDetails, 1);

            $orderPayment
                ->setCcType($paymentTokenDetails['cc_type'])
                ->setCcLast4($paymentTokenDetails['cc_last_4'])
                ->setCcExpMonth($paymentTokenDetails['cc_exp_month'])
                ->setCcExpYear($paymentTokenDetails['cc_exp_year']);

            $params['userPaymentOption'] = [
                'CVV' => $orderPayment->getCcCid(),
                'userPaymentOptionId' => $paymentToken->getGatewayToken(),
            ];
        }

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
                'parent_request_id' => $tokenRequest->getRequestId(),
                'increment_id' => $order->getIncrementId(),
            ]
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
     * @return Cc
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processCardTokenization()
    {
        $orderPayment = $this->orderPayment;

        $ccTokenize = $orderPayment->getAdditionalInformation(Payment::KEY_CC_SAVE);
        $orderPayment->unsAdditionalInformation(Payment::KEY_CC_SAVE);

        if (!$ccTokenize) {
            return $this;
        }

        $cardPaymentToken = $this->cardTokenizationService
            ->setOrderPayment($orderPayment)
            ->processCardPaymentToken();

        $this->orderPayment->setAdditionalInformation(
            Payment::KEY_CC_TOKEN,
            $cardPaymentToken->getPublicHash()
        );

        return $this;
    }
}
