<?php

namespace Nuvei\Payments\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction as OrderTransaction;
use Nuvei\Payments\Lib\Http\Client\Curl;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Config;
use Nuvei\Payments\Model\Logger as SafechargeLogger;
use Nuvei\Payments\Model\Payment;
use Nuvei\Payments\Model\Request\AbstractPayment;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;
use Nuvei\Payments\Model\Request\Payment\Factory as PaymentRequestFactory;
use Nuvei\Payments\Model\RequestInterface;
use Nuvei\Payments\Model\Response\Factory as ResponseFactory;

/**
 * Nuvei Payments refund payment request model.
 */
class Refund extends AbstractPayment implements RequestInterface
{
    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;
	private $request;

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
        $amount = 0.0,
		\Magento\Framework\App\Request\Http $request
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

        $this->transactionRepository	= $transactionRepository;
        $this->request					= $request;
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
        $orderPayment   = $this->orderPayment;
        $order          = $orderPayment->getOrder();
        $transactionId	= $orderPayment->getRefundTransactionId();
//		$nuvei_data		= $orderPayment->getAdditionalInformation('nuvei');
        
//		$this->config->createLog($nuvei_data, '$nuvei_data');
//		$this->config->createLog($this->request->getParam('invoice_id'), 'invoice_id');
		
//		$orderdetails = $order->loadByIncrementId($order->getId());
//		foreach ($orderdetails->getInvoiceCollection() as $invoice)
//        {
//			$this->config->createLog($invoice->getIncrementId(), 'refund $invoice_id'); 
//        }
		
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

		$sale_settle_params	= $orderPayment->getAdditionalInformation(Payment::SALE_SETTLE_PARAMS);
		$this->config->createLog($sale_settle_params, 'Refund sale_settle_params');
		
        $payment_method		= $orderPayment->getAdditionalInformation(Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD);

        if (empty($sale_settle_params['AuthCode']) && Payment::APM_METHOD_CC == $payment_method) {
            $msg = __('Transaction does not contain authorization code.');
            $this->config->createLog($msg);
            throw new PaymentException($msg);
        }

        $params = [
            'clientUniqueId'        => $order->getIncrementId(),
            'currency'              => $order->getBaseCurrencyCode(),
            'amount'                => (float)$this->amount,
            'relatedTransactionId'    => $transactionId,
            'authCode'              => empty($sale_settle_params['AuthCode']) ? '' : $sale_settle_params['AuthCode'],
            'comment'               => '',
            'merchant_unique_id'    => $order->getIncrementId(),
            'urlDetails'            => [
                'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
            ],
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
