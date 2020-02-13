<?php

namespace Safecharge\Safecharge\Controller\Payment\Callback;

use Magento\Checkout\Model\Session as CheckoutSession;
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

        $this->orderFactory                = $orderFactory;
        $this->moduleConfig                = $moduleConfig;
        $this->authorizeCommand            = $authorizeCommand;
        $this->captureCommand            = $captureCommand;
        $this->safechargeLogger            = $safechargeLogger;
        $this->paymentRequestFactory    = $paymentRequestFactory;
        $this->dataObjectFactory        = $dataObjectFactory;
        $this->cartManagement            = $cartManagement;
        $this->checkoutSession            = $checkoutSession;
        $this->onepageCheckout            = $onepageCheckout;
        $this->jsonResultFactory        = $jsonResultFactory;
        $this->transaction                = $transaction;
        $this->invoiceService            = $invoiceService;
        $this->invoiceRepository        = $invoiceRepository;
        $this->transObj                    = $transObj;
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
            $this->echo_result('DMN Error - SafeCharge payment module is not active!');
        }
        
        try {
            $params = array_merge(
                $this->getRequest()->getParams(),
                $this->getRequest()->getPostValue()
            );
            
            $status = !empty($params['Status']) ? strtolower($params['Status']) : null;

            $this->moduleConfig->createLog($params, 'DMN params:');
            $this->validateChecksum($params);
            
            if (empty($params['transactionType'])) {
                $this->echo_result('DMN error - missing Transaction Type.');
            }
            
            if (empty($params['TransactionID'])) {
                $this->echo_result('DMN error - missing Transaction ID.');
            }

            if (!empty($params["order"])) {
                $orderIncrementId = $params["order"];
            } elseif (!empty($params["merchant_unique_id"]) && intval($params["merchant_unique_id"]) != 0) {
                $orderIncrementId = $params["merchant_unique_id"];
            } elseif (!empty($params["orderId"])) {
                $orderIncrementId = $params["orderId"];
            } else {
                $this->moduleConfig->createLog('DMN error - no order id parameter.');
                $this->echo_result('DMN error - no order id parameter.');
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
            } while ($tryouts <=10 && !($order && $order->getId()));

            # try to create the order
            if (!($order && $order->getId())) {
                $this->moduleConfig->createLog('Order '. $orderIncrementId .' not found, try to create it!');
                
                $result = $this->placeOrder();
                
                if ($result->getSuccess() !== true) {
                    $this->moduleConfig->createLog($result->getErrorMessage(), 'DMN Callback error - place order error:');
                    $this->echo_result('DMN Callback error - place order error:' . $result->getErrorMessage());
                }
                
                $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
                
                $this->moduleConfig->createLog('An Order with ID '. $orderIncrementId .' was created in the DMN page.');
            }
            # try to create the order END
            
            $this->moduleConfig->createLog('DMN try ' . $tryouts . ', there IS order.');
            $this->moduleConfig->createLog($status, 'DMN with status:');

            /** @var OrderPayment $payment */
            $orderPayment    = $order->getPayment();
            $order_status    = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_STATUS);
            $order_tr_type    = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_TYPE);
            
            $tr_type_param    = strtolower($params['transactionType']);
            
            if (strtolower($order_tr_type) == $tr_type_param
                && strtolower($order_status) == 'approved'
                && $order_status != $params['Status']
            ) {
                $msg = 'Current Order status is "'. $order_status .'", but incoming DMN status is "'
                    . $params['Status'] . '", for Transaction type '. $order_tr_type
                    .'. Do not apply DMN data on the Order!';
                
                $this->moduleConfig->createLog($msg);
                $this->echo_result($msg);
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
            
            if (in_array($status, ['approved', 'success'])) {
                $message                = $this->captureCommand->execute($orderPayment, $order->getBaseGrandTotal(), $order);
                $sc_transaction_type    = Payment::SC_PROCESSING;
                $is_closed                = false;
                $refund_msg                = '';
                $is_partial_settle        = false;
                
                if ($tr_type_param == 'auth') {
                    $transactionType        = Transaction::TYPE_AUTH;
                    $sc_transaction_type    = Payment::SC_AUTH;
                    
                    $orderPayment->setAdditionalInformation(
                        Payment::AUTH_PARAMS,
                        [
                            'TransactionID'    => $params['TransactionID'],
                            'AuthCode'        => $params['AuthCode'],
                        ]
                    );
                    
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

                    $tr_type    = $orderPayment->addTransaction($transactionType);
                    $msg        = $orderPayment->prependMessage($message);

                    $orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
                } elseif (in_array($tr_type_param, ['sale', 'settle'])) {
                    $transactionType        = Transaction::TYPE_CAPTURE;
                    $sc_transaction_type    = Payment::SC_SETTLED;
                    $invCollection            = $order->getInvoiceCollection();
                    $inv_amount                = $order->getBaseGrandTotal();
                        
                    if ('settle' == $tr_type_param
                        && round(floatval($params['item_amount_1']), 2)
                            - round(floatval($params['totalAmount']), 2) > 0.00
                    ) {
                        $sc_transaction_type    = Payment::SC_PARTIALLY_SETTLED;
                        $inv_amount                = round(floatval($params['totalAmount']), 2);
                        $is_partial_settle        = true;
                    }
                    
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

                        // Save the invoice
                        $this->invoiceRepository->save($invoice);
                        
                        // create fake Auth before the Settle for sale ONLY
                        if ('sale' == $tr_type_param) {
                            $this->moduleConfig->createLog('Sale - create an Auth transaction');

                            $orderPayment
                                ->setIsTransactionPending(false)
                                ->setIsTransactionClosed(false)
                                ->setParentTransactionId(null);

                            $transaction = $this->transObj->setPayment($orderPayment)
                                ->setOrder($order)
                                ->setTransactionId(!empty($params['relatedTransactionId'])
                                    ? $params['relatedTransactionId'] : uniqid())
                                ->setFailSafe(true)
                                ->build(Transaction::TYPE_AUTH);

                            $transaction->save();

                            $tr_type    = $orderPayment->addTransaction(Transaction::TYPE_AUTH);
                            $msg        = $orderPayment->prependMessage($message);

                            $orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
                            $orderPayment->save();
                        }
                        
                        $orderPayment
                            ->setIsTransactionPending($status === "pending" ? true: false)
                            ->setIsTransactionClosed(false)
                            ->setParentTransactionId(!empty($params['relatedTransactionId'])
                                ? $params['relatedTransactionId'] : null);
                        
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
                    } elseif (!$order->canInvoice()) {
                        $this->moduleConfig->createLog('We can NOT create invoice.');
                    }
                } elseif (in_array($tr_type_param, ['void', 'voidcredit'])) {
                    $transactionType        = Transaction::TYPE_VOID;
                    $sc_transaction_type    = Payment::SC_VOIDED;
                    $is_closed                = true;
                    
                    $order->setData('state', Order::STATE_CLOSED);
                } elseif (in_array($tr_type_param, ['credit', 'refund'])) {
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
                            . "<b>" . number_format($inv_amount, 2, '.', '') . ' ' . $params['currency'] . "</b>, "
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
            } elseif (in_array($status, ['declined', 'error'])) {
                $params['ErrCode']        = (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
                $params['ExErrCode']    = (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
                
                $order->addStatusHistoryComment(
                    __("The {$params['transactionType']} request returned '{$params['Status']}' status "
                    . "(Code: {$params['ErrCode']}, Reason: {$params['ExErrCode']}).")
                );
            }
            
            $orderPayment->save();
            $order->save();
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            $this->moduleConfig->createLog($e->getMessage() . "\n\r" . $e->getTraceAsString(), 'DMN Excception:');
            $this->echo_result('Error: ' . $e->getMessage());
        }

        $this->moduleConfig->createLog('DMN process end for order #' . $orderIncrementId);
        $this->echo_result('DMN with status '. $status .' process completed.');
    }
    
    /**
     * Function echo_result
     * Sent string to the output instead of echo php function
     *
     * @param type $string
     */
    private function echo_result($string)
    {
        $response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $response->setContents($string);
        return $response;
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
            } else {
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
