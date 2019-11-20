<?php 

namespace Safecharge\Safecharge\Controller\Payment\Callback;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Safecharge Safecharge payment redirect controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Dmn extends Action implements CsrfAwareActionInterface
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var AuthorizeCommand
     */
    private $authorizeCommand;

    /**
     * @var CaptureCommand
     */
    private $captureCommand;

    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var PaymentRequestFactory
     */
    private $paymentRequestFactory;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Onepage
     */
    private $onepageCheckout;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * Object constructor.
     *
     * @param Context                 $context
     * @param PaymentRequestFactory   $paymentRequestFactory
     * @param OrderFactory            $orderFactory
     * @param ModuleConfig            $moduleConfig
     * @param AuthorizeCommand        $authorizeCommand
     * @param CaptureCommand          $captureCommand
     * @param SafechargeLogger        $safechargeLogger
     * @param DataObjectFactory       $dataObjectFactory
     * @param CartManagementInterface $cartManagement
     * @param CheckoutSession         $checkoutSession
     * @param Onepage                 $onepageCheckout
     * @param JsonFactory             $jsonResultFactory
     */
    public function __construct(
        Context $context,
        PaymentRequestFactory $paymentRequestFactory,
        OrderFactory $orderFactory,
        ModuleConfig $moduleConfig,
        AuthorizeCommand $authorizeCommand,
        CaptureCommand $captureCommand,
        SafechargeLogger $safechargeLogger,
        DataObjectFactory $dataObjectFactory,
        CartManagementInterface $cartManagement,
        CheckoutSession $checkoutSession,
        Onepage $onepageCheckout,
        JsonFactory $jsonResultFactory
    ) {
        parent::__construct($context);

        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->authorizeCommand = $authorizeCommand;
        $this->captureCommand = $captureCommand;
        $this->safechargeLogger = $safechargeLogger;
        $this->paymentRequestFactory = $paymentRequestFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cartManagement = $cartManagement;
        $this->checkoutSession = $checkoutSession;
        $this->onepageCheckout = $onepageCheckout;
        $this->jsonResultFactory = $jsonResultFactory;
    }
	
	/** 
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request 
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @return JsonFactory
     */
    public function execute()
    {
        if (!$this->moduleConfig->isActive()) {
			echo 'DMN Error - SafeCharge payment module is not active!';
			return;
		}
		
		try {
			$params = array_merge(
				$this->getRequest()->getParams(),
				$this->getRequest()->getPostValue()
			);

			$this->moduleConfig->createLog($params, 'DMN params:');

			$this->validateChecksum($params);

			if (isset($params["merchant_unique_id"]) && $params["merchant_unique_id"]) {
				$orderIncrementId = $params["merchant_unique_id"];
			}
			elseif (isset($params["order"]) && $params["order"]) {
				$orderIncrementId = $params["order"];
			}
			elseif (isset($params["orderId"]) && $params["orderId"]) {
				$orderIncrementId = $params["orderId"];
			}
			else {
				$this->moduleConfig->createLog('DMN error - no order id parameter.');
				
				echo 'DMN error - no order id parameter.';
				return;
			}

			$tryouts = 0;
			do {
				$tryouts++;

				/** @var Order $order */
				$order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);

				if (!($order && $order->getId())) {
					$this->moduleConfig->createLog('DMN try ' . $tryouts . ' there is NO order yet.');
					sleep(3);
				}
			}
			while ($tryouts <=10 && !($order && $order->getId()));

			if (!($order && $order->getId())) {
				echo 'Order '. $orderIncrementId .' not found!';
				return;
			}
			
			$this->moduleConfig->createLog('DMN try ' . $tryouts . ' there IS order.');

			/** @var OrderPayment $payment */
			$orderPayment = $order->getPayment();

			$transactionId = @$params['TransactionID'];
			
			if ($transactionId) {
				$orderPayment->setAdditionalInformation(
					Payment::TRANSACTION_ID,
					$transactionId
				);
			}

			if (!empty($params['AuthCode'])) {
				$orderPayment->setAdditionalInformation(
					Payment::TRANSACTION_AUTH_CODE_KEY,
					$params['AuthCode']
				);
			}

			if (!empty($params['payment_method'])) {
				$orderPayment->setAdditionalInformation(
					Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD,
					$params['payment_method']
				);
			}
			
			$orderPayment->setTransactionAdditionalInfo(
				Transaction::RAW_DETAILS,
				$params
			);

			$status = !empty($params['Status']) ? strtolower($params['Status']) : null;

			if (in_array($status, ['declined', 'error'])) {
				$params['ErrCode']		= (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
				$params['ExErrCode']	= (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
				
				$order->addStatusHistoryComment("Payment returned a '{$params['Status']}' status "
					. "(Code: {$params['ErrCode']}, Reason: {$params['ExErrCode']}).");
			}
			elseif ($status) {
				$order->addStatusHistoryComment("Payment returned a '" . $params['Status'] . "' status");
			}

			if ($status === "pending") {
				$order->setState(Order::STATE_NEW)->setStatus('pending');
			}

			if (
				in_array($status, ['approved', 'success'])
//				&& $orderPayment->getAdditionalInformation(Payment::KEY_CHOSEN_APM_METHOD) !== Payment::APM_METHOD_CC
			) {
//				$params['transactionType'] = isset($params['transactionType']) ? $params['transactionType'] : null;
//				$invoiceTransactionId = $transactionId;
				$transactionType = Transaction::TYPE_AUTH;
				$sc_transaction_type = Payment::SC_AUTH;
				$isSettled = false;

				switch (strtolower($params['transactionType'])) {
					case 'auth':
//						$request = $this->paymentRequestFactory->create(
//							AbstractRequest::PAYMENT_SETTLE_METHOD,
//							$orderPayment,
//							$order->getBaseGrandTotal()
//						);
//						$settleResponse = $request->process();
//						$invoiceTransactionId = $settleResponse->getTransactionId();
						$message = $this->captureCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
//						$transactionType = Transaction::TYPE_CAPTURE;
//						$isSettled = true;
						
						break;
						
					case 'sale':
					case 'settle':
						$message = $this->captureCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
						$transactionType = Transaction::TYPE_CAPTURE;
						$sc_transaction_type = Payment::SC_SETTLED;
						$isSettled = true;
						
						break;
				}

				$orderPayment
//					->setTransactionId($transactionId)
					->setIsTransactionPending($status === "pending" ? true: false)
					->setIsTransactionClosed($isSettled ? 1 : 0);

				if ($transactionType === Transaction::TYPE_CAPTURE) {
					$this->moduleConfig->createLog('DMN create Invoice.');
					
					/** @var Invoice $invoice */
					foreach ($order->getInvoiceCollection() as $invoice) {
						$invoice
//							->setTransactionId($invoiceTransactionId)
							->setTransactionId($transactionId)
							->pay()
							->save();
					}
				}
				
				$transaction	= $orderPayment->addTransaction($transactionType);
//				$transaction	= $orderPayment->addTransaction($sc_transaction_type);
				$message		= $orderPayment->prependMessage($message);
				
				$orderPayment->addTransactionCommentsToOrder(
					$transaction,
					$message
				);
			}

			$orderPayment->save();
			$order->save();
		}
		catch (\Exception $e) {
			$msg = $e->getMessage();

			$this->moduleConfig->createLog($e->getMessage(), 'DMN Excception:');
//			$this->moduleConfig->createLog($e->getTraceAsString());

			echo 'Error: ' . $e->getMessage();
			return;
		}

		$this->moduleConfig->createLog($orderIncrementId, 'DMN Success for order #');

		echo 'SUCCESS';
		return;
    }

    private function validateChecksum($params)
    {
        if (!isset($params["advanceResponseChecksum"])) {
            throw new \Exception(
                __('Required key advanceResponseChecksum for checksum calculation is missing.')
            );
        }
        
		$concat = $this->moduleConfig->getMerchantSecretKey();
        
		foreach (['totalAmount', 'currency', 'responseTimeStamp', 'PPP_TransactionID', 'Status', 'productId'] as $checksumKey) {
            if (!isset($params[$checksumKey])) {
                throw new \Exception(
                    __('Required key %1 for checksum calculation is missing.', $checksumKey)
                );
            }

            if (is_array($params[$checksumKey])) {
                foreach ($params[$checksumKey] as $subKey => $subVal) {
                    $concat .= $subVal;
                }
            }
			else {
                $concat .= $params[$checksumKey];
            }
        }

        $checksum = hash($this->moduleConfig->getHash(), utf8_encode($concat));
        
        if ($params["advanceResponseChecksum"] !== $checksum) {
            throw new \Exception(
                __('Checksum validation failed!')
            );
        }

        return true;
    }
}
