<?php

namespace Safecharge\Safecharge\Controller\Payment\Callback;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Safecharge\Safecharge\Model\Payment;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Safecharge Safecharge payment redirect controller.
 */
class DmnOld extends \Magento\Framework\App\Action\Action
{
    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var CaptureCommand
     */
    private $captureCommand;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;
    
    private $transaction;
    private $invoiceService;
    private $invoiceRepository;
    private $transObj;
    private $quoteFactory;
    private $request;
	private $orderRepo;
    private $searchCriteriaBuilder;
	private $orderResourceModel;

    /**
     * Object constructor.
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Safecharge\Safecharge\Model\Config $moduleConfig,
        \Magento\Sales\Model\Order\Payment\State\CaptureCommand $captureCommand,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transObj,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Event\ManagerInterface $eventManager,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepo,
		\Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
		\Magento\Sales\Model\ResourceModel\Order $orderResourceModel
    ) {
        $this->moduleConfig                = $moduleConfig;
        $this->captureCommand            = $captureCommand;
        $this->dataObjectFactory        = $dataObjectFactory;
        $this->cartManagement            = $cartManagement;
        $this->jsonResultFactory        = $jsonResultFactory;
        $this->transaction                = $transaction;
        $this->invoiceService            = $invoiceService;
        $this->invoiceRepository        = $invoiceRepository;
        $this->transObj                    = $transObj;
        $this->quoteFactory                = $quoteFactory;
        $this->request                    = $request;
        $this->_eventManager            = $eventManager;
        $this->orderRepo            = $orderRepo;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderResourceModel = $orderResourceModel;
        
        parent::__construct($context);
    }
    
    /**
     * @return JsonFactory
     */
    public function execute()
    {
        $jsonOutput = $this->jsonResultFactory->create();
        $jsonOutput->setHttpResponseCode(200);
        
        if (!$this->moduleConfig->isActive()) {
            $jsonOutput->setData('DMN Error - SafeCharge payment module is not active!');
            return $jsonOutput;
        }
        
        try {
            $params = array_merge(
                $this->request->getParams(),
                $this->request->getPostValue()
            );
            
            $status = !empty($params['Status']) ? strtolower($params['Status']) : null;

            $this->moduleConfig->createLog($params, 'DMN params:');
//            $this->moduleConfig->createLog(http_build_query($params), 'DMN params:');
			
            $this->validateChecksum($params);
            
            if (empty($params['transactionType'])) {
                $this->moduleConfig->createLog('DMN error - missing Transaction Type.');
                
                $jsonOutput->setData('DMN error - missing Transaction Type.');
                return $jsonOutput;
            }
            
            if (in_array($params['transactionType'], ['Auth', 'Sale']) && $status === 'declined') {
                $this->moduleConfig->createLog('DMN message - Declined Order, process stops here.');
                
                $jsonOutput->setData('DMN error - Declined Order, process stops here.');
                return $jsonOutput;
            }
            
            if (empty($params['TransactionID'])) {
                $this->moduleConfig->createLog('DMN error - missing Transaction ID.');
                
                $jsonOutput->setData('DMN error - missing Transaction ID.');
                return $jsonOutput;
            }
            
            if (!empty($params["order"])) {
                $orderIncrementId = $params["order"];
            } elseif (!empty($params["merchant_unique_id"]) && intval($params["merchant_unique_id"]) != 0) {
                $orderIncrementId = $params["merchant_unique_id"];
            } elseif (!empty($params["orderId"])) {
                $orderIncrementId = $params["orderId"];
            } else {
                $this->moduleConfig->createLog('DMN error - no Order ID parameter.');
                
                $jsonOutput->setData('DMN error - no Order ID parameter.');
                return $jsonOutput;
            }
			
			$searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $orderIncrementId, 'eq')->create();

            $tryouts = 0;
            do {
                $tryouts++;
				$orderList = $this->orderRepo->getList($searchCriteria)->getItems();

                if (!$orderList || empty($orderList)) {
                    $this->moduleConfig->createLog('DMN try ' . $tryouts
                        . ' there is NO order for TransactionID ' . $params['TransactionID'] . ' yet.');
                    sleep(3);
                }
				else {
					$order = current($orderList);
				}
            } while ( $tryouts < 5 && (empty($order) || empty($orderList)) );

            # try to create the order
            if (!$orderList || empty($orderList)) {
                $this->moduleConfig->createLog('Order '. $orderIncrementId .' not found, try to create it!');
                
                $result = $this->placeOrder($params);
                
                if ($result->getSuccess() !== true) {
                    $this->moduleConfig->createLog('DMN Callback error - place order error: ' . $result->getMessage());
                    
                    $jsonOutput->setData('DMN Callback error - place order error: ' . $result->getMessage());
                    return $jsonOutput;
                }
                
				$orderList = $this->orderRepo->getList($searchCriteria)->getItems();
                $this->moduleConfig->createLog('An Order with ID '. $orderIncrementId .' was created in the DMN page.');
            }
            # try to create the order END
            
            if (!$orderList || empty($orderList)) {
                $jsonOutput->setData('DMN Callback error - there is no Order and the code did not success to made it.');
                return $jsonOutput;
            }
			
			$order			= current($orderList);
            $orderPayment    = $order->getPayment();
            $order_status    = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_STATUS);
            $order_tr_type    = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_TYPE);
            
            $tr_type_param    = strtolower($params['transactionType']);
            
            // do not overwrite Order status
            if ('auth' === $tr_type_param
                && round(floatval($order->getBaseGrandTotal()), 2) != round(floatval($params['totalAmount']), 2)
            ) {
                $msg = 'The DMN total amount (' . round(floatval($params['totalAmount']), 2)
                    .') is different than Order total amount (' . round(floatval($order->getBaseGrandTotal()), 2)
                    . '). The process stops here!';
                
                $this->moduleConfig->createLog($msg);
                $jsonOutput->setData($msg);
                return $jsonOutput;
            }
            
            if (strtolower($order_tr_type) == $tr_type_param
                && strtolower($order_status) == 'approved'
                && $order_status != $params['Status']
            ) {
                $msg = 'Current Order status is "'. $order_status .'", but incoming DMN status is "'
                    . $params['Status'] . '", for Transaction type '. $order_tr_type
                    .'. Do not apply DMN data on the Order!';
                
                $this->moduleConfig->createLog($msg);
                $jsonOutput->setData($msg);
                return $jsonOutput;
            }
            
            if (in_array(strtolower($order_tr_type), ['credit', 'refund', 'void'])
                && strtolower($order_status) == 'approved'
            ) {
                $msg = 'No more actions are allowed for order #' . $order->getId();
                
                $this->moduleConfig->createLog($msg);
                $jsonOutput->setData($msg);
                return $jsonOutput;
            }
            
            if ($tr_type_param === 'auth' && strtolower($order_tr_type) === 'settle') {
                $msg = 'Can not set Auth to Settled Order #' . $order->getId();
                
                $this->moduleConfig->createLog($msg);
                $jsonOutput->setData($msg);
                return $jsonOutput;
            }
            // do not overwrite Order status END

            // add data to the Payment
            $parent_trans_id = empty($params['relatedTransactionId']) ? null : $params['relatedTransactionId'];
            
            $orderPayment
                ->setTransactionId($params['TransactionID'])
                ->setParentTransactionId($parent_trans_id)
                ->setAuthCode($params['AuthCode']);
            
            /* TODO old remove it */
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
            
            if (!empty($params['userPaymentOptionId'])) {
                $orderPayment->setAdditionalInformation(
                    'upoID',
                    $params['userPaymentOptionId']
                );
            }
            
            /* TODO old search for use and remove it */
            $orderPayment->setTransactionAdditionalInfo(
                Transaction::RAW_DETAILS,
                $params
            );

            if ($status === "pending") {
                $order
                    ->setState(Order::STATE_NEW)
                    ->setStatus('pending');
            }
            
            if (in_array($status, ['approved', 'success'])) {
                $message                = $this->captureCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
                $sc_transaction_type    = Payment::SC_PROCESSING;
                $is_closed                = false;
                $refund_msg                = '';
                $is_partial_settle        = false;
                
                if ($tr_type_param == 'auth') {
                    $transactionType        = Transaction::TYPE_AUTH;
                    $sc_transaction_type    = Payment::SC_AUTH;
                    
                    /* TODO old - test and remov it */
                    $orderPayment->setAdditionalInformation(
                        Payment::AUTH_PARAMS,
                        [
                            'TransactionID'    => $params['TransactionID'],
                            'AuthCode'        => $params['AuthCode'],
                            'totalAmount'        => $params['totalAmount'],
                        ]
                    );
                    
                    $orderPayment
                        ->setAuthAmount($params['totalAmount'])
                        ->setIsTransactionPending(false)
                        ->setIsTransactionClosed(0);

                    // set transaction
                    $transaction = $this->transObj->setPayment($orderPayment)
                        ->setOrder($order)
                        ->setTransactionId($params['TransactionID'])
                        ->setFailSafe(true)
                        ->build($transactionType);

                    $transaction->save();

                    $tr_type    = $orderPayment->addTransaction($transactionType);
                    $msg        = $orderPayment->prependMessage($message);

                    $orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
                }
				elseif (in_array($tr_type_param, ['sale', 'settle'])) {
                    $transactionType        = Transaction::TYPE_CAPTURE;
                    $sc_transaction_type    = Payment::SC_SETTLED;
                    $invCollection            = $order->getInvoiceCollection();
                    $inv_amount                = round(floatval($order->getBaseGrandTotal()), 2);
                        
                    if ('settle' == $tr_type_param
                        && ($inv_amount - round(floatval($params['totalAmount']), 2) > 0.00)
                    ) {
                        $sc_transaction_type    = Payment::SC_PARTIALLY_SETTLED;
                        $inv_amount                = round(floatval($params['totalAmount']), 2);
                        $is_partial_settle        = true;
                    }
                    
                    $orderPayment->setSaleSettleAmount($inv_amount);
                    
                    if (count($invCollection) > 0) {
                        $this->moduleConfig->createLog('There is/are invoice/s');
                        
                        foreach ($order->getInvoiceCollection() as $invoice) {
                            $invoice
                                ->setTransactionId($params['TransactionID'])
                                ->pay()
                                ->save();
                        }
                    }
                    // create Invoicees and Transactions for non-Magento actions
                    elseif ($order->canInvoice()
                        && (
                            'sale' == $tr_type_param // Sale flow
                            || (
                                $params["order"] == $params["merchant_unique_id"]
                                && $params["payment_method"] != 'cc_card'
                            ) // APMs flow
                            || (
                                !empty($params["merchant_unique_id"])
                                && $params["merchant_unique_id"] != $params["order"]
                            ) // CPanel Settle
                        )
                    ) {
                        $this->moduleConfig->createLog('DMN - we can create invoice.');
                        
                        // Prepare the invoice
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE)
                            ->setTransactionId($params['TransactionID'])
                            ->setState(Invoice::STATE_PAID)
                            ->setBaseGrandTotal($inv_amount);

                        $invoice->register();
                        $invoice->getOrder()->setIsInProcess(true);
                        $invoice->pay();

                        // Create the transaction
                        $transactionSave = $this->transaction
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder());
                        $transactionSave->save();

                        // Update the order
                        $order->setTotalPaid($inv_amount);
                        $order->setBaseTotalPaid($inv_amount);

						// create fake Auth before the Settle for sale ONLY
                        if ('sale' == $tr_type_param && $params["payment_method"] == 'cc_card') {
							$invoice->setCanVoidFlag(true);
							$order->setCanVoidPayment(true);
							$orderPayment->setCanVoid(true);
                        }
						
                        // Save the invoice
                        $this->invoiceRepository->save($invoice);
                        
                        $orderPayment
                            ->setIsTransactionPending(0)
                            ->setIsTransactionClosed(0);
						
                        // set transaction
                        $transaction = $this->transObj->setPayment($orderPayment)
                            ->setOrder($order)
                            ->setTransactionId($params['TransactionID'])
                            ->setFailSafe(true)
                            ->build($transactionType);
        
                        $transaction->save();
						
                        $tr_type    = $orderPayment->addTransaction($transactionType);
                        $msg        = $orderPayment->prependMessage($message);
						
                        $orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
                    }
					elseif (!$order->canInvoice()) {
                        $this->moduleConfig->createLog('We can NOT create invoice.');
                    }
                }
				elseif (in_array($tr_type_param, ['void', 'voidcredit'])) {
                    $transactionType        = Transaction::TYPE_VOID;
                    $sc_transaction_type    = Payment::SC_VOIDED;
                    $is_closed                = true;
                    
                    $order->setData('state', Order::STATE_CLOSED);
                }
				elseif (in_array($tr_type_param, ['credit', 'refund'])) {
                    $orderPayment->setAdditionalInformation(
                        Payment::REFUND_TRANSACTION_AMOUNT,
                        $params['totalAmount']
                    );
                    
                    $transactionType        = Transaction::TYPE_REFUND;
                    $sc_transaction_type    = Payment::SC_REFUNDED;
                    
                    if ((!empty($params['totalAmount']) && 'cc_card' == $params["payment_method"])
                    || false !== strpos($params["merchant_unique_id"], 'gwp')
                    ) {
                        $refund_msg = '<br/>The Refunded amount is <b>'
                            . $params['totalAmount'] . ' ' . $params['currency'] . '</b>.';
                    }
                    
                    $order->setData('state', Order::STATE_PROCESSING);
                }
                
                $order->setStatus($sc_transaction_type);
                
                if ($is_partial_settle) {
                    $order->addStatusHistoryComment(
                        __("The <b>Partial Settle</b> request for amount of "
                            . "<b>" . $inv_amount . ' ' . $params['currency'] . "</b>, "
                            . "returned <b>" . $params['Status'] . "</b> status.<br/>"
                            . 'Transaction ID: ' . $params['TransactionID'] .', Related Transaction ID: ')
                            . $params['relatedTransactionId'] . $refund_msg,
                        $sc_transaction_type
                    );
                } else {
                    $order->addStatusHistoryComment(
                        __("The <b>{$params['transactionType']}</b> request returned <b>" . $params['Status'] . "</b> status."
                            . '<br/>Transaction ID: ' . $params['TransactionID'] .', Related Transaction ID: ')
                            . $params['relatedTransactionId'] . $refund_msg,
                        $sc_transaction_type
                    );
                }
            }
			elseif (in_array($status, ['declined', 'error'])) {
                $params['ErrCode']        = (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
                $params['ExErrCode']    = (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
                
                $order->addStatusHistoryComment(
                    __("The {$params['transactionType']} request returned '{$params['Status']}' status "
                    . "(Code: {$params['ErrCode']}, Reason: {$params['ExErrCode']}).")
                );
            }
            
            $orderPayment->save();
			$this->moduleConfig->createLog('18');
			$this->orderResourceModel->save($order);
			
			$this->moduleConfig->createLog('19');
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $this->moduleConfig->createLog($e->getMessage() . "\n\r" . $e->getTraceAsString(), 'DMN Excception:');
            
            $jsonOutput->setData('Error: ' . $e->getMessage());
			
			$order->addStatusHistoryComment($msg);
			
            return $jsonOutput;
        }

        $this->moduleConfig->createLog('DMN process end for order #' . $orderIncrementId);
        
        $jsonOutput->setData('DMN process end for order #' . $orderIncrementId);
        return $jsonOutput;
    }
    
    /**
     * Place order.
     */
    private function placeOrder($params)
    {
        $this->moduleConfig->createLog('PlaceOrder()');
        
        $result    = $this->dataObjectFactory->create();
        
        if (empty($params['quote'])) {
            return $result
                ->setData('error', true)
                ->setData('message', 'Missing Quote parameter.');
        }
        
        try {
            $quote    = $this->quoteFactory->create()->loadByIdWithoutStore((int) $params['quote']);

            if (intval($quote->getIsActive()) == 0) {
                $this->moduleConfig->createLog($quote->getQuoteId(), 'Quote ID');

                return $result
                    ->setData('error', true)
                    ->setData('message', 'Quote is not active.');
            }

            if ($quote->getPayment()->getMethod() !== Payment::METHOD_CODE) {
                return $result
                    ->setData('error', true)
                    ->setData('message', 'Quote payment method is "'
                        . $quote->getPayment()->getMethod() . '"');
            }

            $params = array_merge(
                $this->request->getParams(),
                $this->request->getPostValue()
            );
            
            $orderId = $this->cartManagement->placeOrder((int) $params['quote']);

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
        } catch (\Exception $exception) {
            $this->moduleConfig->createLog($exception->getMessage(), 'DMN placeOrder Exception: ');
            
            return $result
                ->setData('error', true)
                ->setData('message', $exception->getMessage());
        }
        
        return $result;
    }
    
    private function validateChecksum($params)
    {
        $result = $this->jsonResultFactory->create();
        $result->setHttpResponseCode(200);
        
        if (!isset($params["advanceResponseChecksum"])) {
            $msg = 'Required key advanceResponseChecksum for checksum calculation is missing.';
            
            $this->moduleConfig->createLog($msg);
            
            $result->setData($msg);
            return $result;
        }
        
        $concat = $this->moduleConfig->getMerchantSecretKey();
        
        foreach (['totalAmount', 'currency', 'responseTimeStamp', 'PPP_TransactionID', 'Status', 'productId'] as $checksumKey) {
            if (!isset($params[$checksumKey])) {
                $msg = 'Required key '. $checksumKey .' for checksum calculation is missing.';
                $this->moduleConfig->createLog($msg);
                
                $result->setData($msg);
                return $result;
            }

            if (is_array($params[$checksumKey])) {
                foreach ($params[$checksumKey] as $subKey => $subVal) {
                    $concat .= $subVal;
                }
            } else {
                $concat .= $params[$checksumKey];
            }
        }

        $checksum = hash($this->moduleConfig->getHash(), utf8_encode($concat));
        
        if ($params["advanceResponseChecksum"] !== $checksum) {
            $msg = 'Checksum validation failed!';
            
            $this->moduleConfig->createLog($msg);
            
            $result->setData($msg);
            return $result;
        }

        return true;
    }
}
