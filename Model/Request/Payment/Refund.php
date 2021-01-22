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
        \Nuvei\Payments\Model\Logger $logger,
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
            $logger,
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
		/**
		 * TODO - we must create a fix, and refund based on Invoice ID not last allowed option!
		 */
		
        $orderPayment			= $this->orderPayment;
		$ord_trans_addit_info	= $orderPayment->getAdditionalInformation(Payment::ORDER_DATA);
        $order					= $orderPayment->getOrder();
		$trans_to_refund_data	= [];
		
		if(!empty($ord_trans_addit_info) && is_array($ord_trans_addit_info)) {
			foreach(array_reverse($ord_trans_addit_info) as $trans) {
				if(
					strtolower($trans[Payment::TRANSACTION_STATUS]) == 'approved'
					&& in_array(strtolower($trans[Payment::TRANSACTION_TYPE]), ['sale', 'settle'])
				) {
					$trans_to_refund_data = $trans;
					break;
				}
			}
		}
		
//		$orderdetails = $order->loadByIncrementId($order->getId());
//		foreach ($orderdetails->getInvoiceCollection() as $invoice)
//        {
//			$this->config->createLog($invoice->getIncrementId(), 'refund $invoice_id'); 
//        }
		
        if (empty($trans_to_refund_data[Payment::TRANSACTION_ID])) {
            $msg = 'Refund Error - Transaction ID is empty.';
            
			$this->config->createLog($trans_to_refund_data, $msg);
            
			throw new PaymentException(__($msg));
        }

        /** @var OrderTransaction $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $trans_to_refund_data[Payment::TRANSACTION_ID],
            $orderPayment->getId(),
            $order->getId()
        );

//		$sale_settle_params	= $orderPayment->getAdditionalInformation(Payment::SALE_SETTLE_PARAMS);
//		$this->config->createLog($sale_settle_params, 'Refund sale_settle_params');
		
        $payment_method = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD);

        if (
			Payment::APM_METHOD_CC == $trans_to_refund_data[Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD]
			&& empty($trans_to_refund_data[Payment::TRANSACTION_AUTH_CODE_KEY])
		) {
            $msg = 'Refund Error - CC Transaction does not contain authorization code.';
            
			$this->config->createLog($trans_to_refund_data, $msg);
			
            throw new PaymentException(__($msg));
        }

        $params = [
            'clientUniqueId'        => $order->getIncrementId(),
            'currency'              => $order->getBaseCurrencyCode(),
            'amount'                => (float)$this->amount,
            'relatedTransactionId'  => $trans_to_refund_data[Payment::TRANSACTION_ID],
            'authCode'              => $trans_to_refund_data[Payment::TRANSACTION_AUTH_CODE_KEY] ?: '',
            'comment'               => '',
            'merchant_unique_id'    => $order->getIncrementId(),
            'urlDetails'            => [
                'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
            ],
        ];

        $params = array_merge_recursive($params, parent::getParams());

//        $this->logger->updateRequest(
//            $this->getRequestId(),
//            ['increment_id' => $order->getIncrementId(),]
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
