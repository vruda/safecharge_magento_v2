<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction as OrderTransaction;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\AbstractPayment;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Safecharge\Safecharge\Model\RequestInterface;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;

/**
 * Safecharge Safecharge refund payment request model.
 */
class Refund extends AbstractPayment implements RequestInterface
{
    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * Refund constructor.
     *
     * @param SafechargeLogger               $safechargeLogger
     * @param Config                         $config
     * @param Curl                           $curl
     * @param RequestFactory                 $requestFactory
     * @param Factory                        $paymentRequestFactory
     * @param ResponseFactory                $responseFactory
     * @param OrderPayment                   $orderPayment
     * @param TransactionRepositoryInterface $transactionRepository
     * @param float                          $amount
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        RequestFactory $requestFactory,
        PaymentRequestFactory $paymentRequestFactory,
        ResponseFactory $responseFactory,
        OrderPayment $orderPayment,
        TransactionRepositoryInterface $transactionRepository,
        $amount = 0.0
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

        $this->transactionRepository = $transactionRepository;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_REFUND_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_REFUND_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        $orderPayment    = $this->orderPayment;
        $order            = $orderPayment->getOrder();
        $transactionId    = $orderPayment->getRefundTransactionId();
        
        if ($transactionId === null) {
            $msg = __('Invoice transaction id has been not provided.');
            $this->config->createLog($msg);
            throw new PaymentException($msg);
        }

        /** @var OrderTransaction $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionId,
            $orderPayment->getId(),
            $order->getId()
        );

        $authCode            = null;
        $transactionDetails    = $transaction->getAdditionalInformation(OrderTransaction::RAW_DETAILS);
        
        if ($transactionDetails) {
            if (!empty($transactionDetails['authCode'])) {
                $authCode = $transactionDetails['authCode'];
            } elseif (!empty($transactionDetails['AuthCode'])) {
                $authCode = $transactionDetails['AuthCode'];
            } else {
                $authCode = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
            }
        }
        
        $payment_method = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD);

        if ($authCode === null && Payment::APM_METHOD_CC == $payment_method) {
            $msg = __('Transaction does not contain authorization code.');
            $this->config->createLog($msg);
            throw new PaymentException($msg);
        }

        $params = [
            'clientUniqueId'        => $order->getIncrementId(),
            'currency'              => $order->getBaseCurrencyCode(),
            'amount'                => (float)$this->amount,
            'relatedTransactionId'    => $transactionId,
            'authCode'              => is_null($authCode) ? '' : $authCode,
            'comment'               => '',
            'merchant_unique_id'    => $order->getIncrementId(),
            'urlDetails'            => [
                'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
            ],
            'sourceApplication'        => $this->config->getSourceApplication(),
        ];

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            ['increment_id' => $order->getIncrementId(),]
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
            'clientUniqueId',
            'amount',
            'currency',
            'relatedTransactionId',
            'authCode',
            'comment',
            'urlDetails',
            'timeStamp',
        ];
    }
}
