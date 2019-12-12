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
	
	private $transaction;
	private $invoiceService;
	private $invoiceRepository;
	private $transObj;

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
        JsonFactory $jsonResultFactory,
		\Magento\Framework\DB\Transaction $transaction,
		\Magento\Sales\Model\Service\InvoiceService $invoiceService,
		\Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
		\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transObj
    ) {
        parent::__construct($context);

        $this->orderFactory				= $orderFactory;
        $this->moduleConfig				= $moduleConfig;
        $this->authorizeCommand			= $authorizeCommand;
        $this->captureCommand			= $captureCommand;
        $this->safechargeLogger			= $safechargeLogger;
        $this->paymentRequestFactory	= $paymentRequestFactory;
        $this->dataObjectFactory		= $dataObjectFactory;
        $this->cartManagement			= $cartManagement;
        $this->checkoutSession			= $checkoutSession;
        $this->onepageCheckout			= $onepageCheckout;
        $this->jsonResultFactory		= $jsonResultFactory;
        $this->transaction				= $transaction;
        $this->invoiceService			= $invoiceService;
        $this->invoiceRepository		= $invoiceRepository;
        $this->transObj					= $transObj;
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
			
			$status = !empty($params['Status']) ? strtolower($params['Status']) : null;

			$this->moduleConfig->createLog($params, 'DMN params:');

			$this->validateChecksum($params);
			
			if(empty($params['transactionType'])) {
				echo 'DMN error - missing Transaction Type.';
				return;
			}
			
			if(empty($params['TransactionID'])) {
				echo 'DMN error - missing Transaction ID.';
				return;
			}

			if (!empty($params["order"])) {
				$orderIncrementId = $params["order"];
			}
			elseif (!empty($params["merchant_unique_id"]) && intval($params["merchant_unique_id"]) != 0) {
				$orderIncrementId = $params["merchant_unique_id"];
			}
			elseif (!empty($params["orderId"])) {
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
				# try to create the order
				$this->moduleConfig->createLog('Order '. $orderIncrementId .' not found, try to create it!');
				
				$result = $this->placeOrder();
				
				if ($result->getSuccess() !== true) {
					$this->moduleConfig->createLog($result->getErrorMessage(), 'DMN Callback error - place order error:');
					echo 'DMN Callback error - place order error:' . $result->getErrorMessage();
					return;
				}
				
				$order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
				
				$this->moduleConfig->createLog('An Order with ID '. $orderIncrementId .' was created in the DMN page.');
				# try to create the order END
				
//				echo 'Order '. $orderIncrementId .' not found!';
//				return;
			}
			
			$this->moduleConfig->createLog('DMN try ' . $tryouts . ', there IS order.');
			$this->moduleConfig->createLog($status, 'DMN with status:');

			/** @var OrderPayment $payment */
			$orderPayment	= $order->getPayment();
			$order_status	= $orderPayment->getAdditionalInformation(Payment::TRANSACTION_STATUS);
			$order_tr_type	= $orderPayment->getAdditionalInformation(Payment::TRANSACTION_TYPE);
			
			if(
				strtolower($order_tr_type) == strtolower($params['transactionType'])
				&& strtolower($order_status) == 'approved'
				&& $order_status != $status
			) {
				$msg = 'Current Order status is "'. $order_status .'", but incoming DMN status is "'
					. $params['Status'] . '", for Transaction type '. $order_tr_type
					.'. Do not apply DMN data on the Order!';
				
				$this->moduleConfig->createLog($msg);
				
				echo $msg;
				return;
			}

			$orderPayment->setAdditionalInformation(
				Payment::TRANSACTION_ID,
				$params['TransactionID']
			);

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
			
			if (!empty($params['Status'])) {
				$orderPayment->setAdditionalInformation(
					Payment::TRANSACTION_STATUS,
					$params['Status']
				);
			}
			
			$orderPayment->setAdditionalInformation(
				Payment::TRANSACTION_TYPE,
				$params['transactionType']
			);
			
			$orderPayment->setTransactionAdditionalInfo(
				Transaction::RAW_DETAILS,
				$params
			);

			if ($status === "pending") {
				$order
					->setState(Order::STATE_NEW)
					->setStatus('pending');
			}
			
			/* TODO - recognize CPanel actions, for them we must create manual transactions */

			if (in_array($status, ['approved', 'success'])) {
				$message = $this->captureCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
				
				if (strtolower($params['transactionType']) == 'auth') {
					$transactionType		= Transaction::TYPE_AUTH;
					$sc_transaction_type	= Payment::SC_AUTH;
					
					$orderPayment
						->setIsTransactionPending(false)
						->setIsTransactionClosed(false)
						->setParentTransactionId(!empty($params['relatedTransactionId']) ? $params['relatedTransactionId'] : null);

					// set transaction
					$transaction = $this->transObj->setPayment($orderPayment)
						->setOrder($order)
						->setTransactionId($params['TransactionID'])
						->setFailSafe(true)
						->build($transactionType);

					$transaction->save();

					$tr_type	= $orderPayment->addTransaction($transactionType);
					$msg		= $orderPayment->prependMessage($message);

					$orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
				}
				elseif (in_array(strtolower($params['transactionType']), ['sale', 'settle'])) {
					$transactionType		= Transaction::TYPE_CAPTURE;
					$sc_transaction_type	= Payment::SC_SETTLED;
					
					$invCollection = $order->getInvoiceCollection();
					
					if(count($invCollection) > 0) {
						$this->moduleConfig->createLog('There is/are invoice/s');
						
						foreach ($order->getInvoiceCollection() as $invoice) {
							$invoice
								->setTransactionId($params['TransactionID'])
								->pay()
								->save();
						}
					}
					// create Invoicees and Transactions for non-Magento actions
					elseif(
						$order->canInvoice()
						&& (
							'Sale' == $params['transactionType'] // Sale flow
							|| (
								@$params["order"] == @$params["merchant_unique_id"]
								&& $params["payment_method"] != 'cc_card'
							) // APMs flow
							|| (
								!empty($params["merchant_unique_id"])
								&& $params["merchant_unique_id"] != @$params["order"]
							) // CPanel Settle
						)
					) {
						$this->moduleConfig->createLog('DMN - we can create invoice.');
						
						// Prepare the invoice
						$invoice = $this->invoiceService->prepareInvoice($order);
						$invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE)
							->setTransactionId($params['TransactionID'])
							->setState(Invoice::STATE_PAID)
							->setBaseGrandTotal($order->getBaseGrandTotal());

						$invoice->register();
						$invoice->getOrder()->setIsInProcess(true);
						$invoice->pay();

						// Create the transaction
						$transactionSave = $this->transaction
							->addObject($invoice)
							->addObject($invoice->getOrder());
						$transactionSave->save(); 

						// Update the order
						$order->setTotalPaid($order->getTotalPaid());
						$order->setBaseTotalPaid($order->getBaseTotalPaid());

						// Save the invoice
						$this->invoiceRepository->save($invoice);
						
						// create fake Auth before the Settle for sale ONLY
						if('Sale' == $params['transactionType']) {
							$this->moduleConfig->createLog('Sale - create an Auth transaction');

							$orderPayment
								->setIsTransactionPending(false)
								->setIsTransactionClosed(false)
								->setParentTransactionId(null);

							$transaction = $this->transObj->setPayment($orderPayment)
								->setOrder($order)
								->setTransactionId(!empty($params['relatedTransactionId']) ? $params['relatedTransactionId'] : uniqid())
								->setFailSafe(true)
								->build(Transaction::TYPE_AUTH);

							$transaction->save();

							$tr_type	= $orderPayment->addTransaction(Transaction::TYPE_AUTH);
							$msg		= $orderPayment->prependMessage($message);

							$orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
							$orderPayment->save();
						}
						
						$orderPayment
							->setIsTransactionPending($status === "pending" ? true: false)
							->setIsTransactionClosed(false)
							->setParentTransactionId(!empty($params['relatedTransactionId']) ? $params['relatedTransactionId'] : null);
						
						// set transaction
						$transaction = $this->transObj->setPayment($orderPayment)
							->setOrder($order)
							->setTransactionId($params['TransactionID'])
							->setFailSafe(true)
							->build($transactionType);
		
						$transaction->save();
						
						$tr_type	= $orderPayment->addTransaction($transactionType);
						$msg		= $orderPayment->prependMessage($message);
						
						$orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
					}
					elseif(!$order->canInvoice()) {
						$this->moduleConfig->createLog('We can NOT create invoice.');
					}
				}
				elseif (strtolower($params['transactionType']) == 'void') {
//					$transactionType		= Transaction::TYPE_VOID;
					$sc_transaction_type	= Payment::SC_VOIDED;
//					$is_closed				= true;
				}
				
				$order->setStatus($sc_transaction_type);
				
				$order->addStatusHistoryComment(
					"The {$params['transactionType']} request returned '" . $params['Status'] . "' status.",
					$sc_transaction_type
				);
			}
			elseif (in_array($status, ['declined', 'error'])) {
				$params['ErrCode']		= (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
				$params['ExErrCode']	= (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
				
				$order->addStatusHistoryComment("The {$params['transactionType']} request returned '{$params['Status']}' status "
					. "(Code: {$params['ErrCode']}, Reason: {$params['ExErrCode']}).");
			}
			
			$orderPayment->save();
			$order->save();
		}
		catch (\Exception $e) {
			$msg = $e->getMessage();

			$this->moduleConfig->createLog($e->getMessage() . "\n\r" . $e->getTraceAsString(), 'DMN Excception:');

			echo 'Error: ' . $e->getMessage();
			return;
		}

		$this->moduleConfig->createLog('DMN process end for order #' . $orderIncrementId);

		echo 'DMN with status '. $status .' process completed.';
		return;
    }
	
	/**
     * Place order.
     *
     * @return DataObject
     */
    private function placeOrder()
    {
        $result = $this->dataObjectFactory->create();
		$params = array_merge(
				$this->getRequest()->getParams(),
				$this->getRequest()->getPostValue()
			);

        try {
            /**
             * Current workaround depends on Onepage checkout model defect
             * Method Onepage::getCheckoutMethod performs setCheckoutMethod
             */
            $this->onepageCheckout->getCheckoutMethod();

            $orderId = $this->cartManagement->placeOrder((int) @$params['quote']);

            $result
                ->setData('success', true)
                ->setData('order_id', $orderId);

            $this->_eventManager->dispatch(
                'safecharge_place_order',
                [
                    'result' => $result,
                    'action' => $this,
                ]
            );
        }
        catch (\Exception $exception) {
			$this->moduleConfig->createLog($exception->getMessage(), 'DMN placeOrder Exception: ');
            
            $result
                ->setData('error', true)
                ->setData(
                    'error_message',
                    __('An error occurred on the server. Please try to place the order again.')
                );
        }
		
		$this->moduleConfig->createLog($result, 'DMN placeOrder result: ');

        return $result;
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
