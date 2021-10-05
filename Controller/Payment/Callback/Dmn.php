<?php

namespace Nuvei\Payments\Controller\Payment\Callback;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Nuvei\Payments\Model\Payment;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Nuvei\Payments\Model\AbstractRequest;

/**
 * Nuvei Payments payment redirect controller.
 */
class Dmn extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\CsrfAwareActionInterface
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
    private $requestFactory;
    private $httpRequest;
    
    // variables for the DMN process
    private $order;
    private $orderPayment;
    private $transactionType;
    private $sc_transaction_type;
    private $jsonOutput;
    private $start_subscr       = false;
    private $is_partial_settle  = false;
    private $curr_trans_info    = []; // collect the info for the current transaction (action)

    /**
     * Object constructor.
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Nuvei\Payments\Model\Config $moduleConfig,
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
        \Magento\Sales\Model\ResourceModel\Order $orderResourceModel,
        \Nuvei\Payments\Model\Request\Factory $requestFactory,
        \Magento\Framework\App\Request\Http $httpRequest
    ) {
        $this->moduleConfig             = $moduleConfig;
        $this->captureCommand           = $captureCommand;
        $this->dataObjectFactory        = $dataObjectFactory;
        $this->cartManagement           = $cartManagement;
        $this->jsonResultFactory        = $jsonResultFactory;
        $this->transaction              = $transaction;
        $this->invoiceService           = $invoiceService;
        $this->invoiceRepository        = $invoiceRepository;
        $this->transObj                 = $transObj;
        $this->quoteFactory             = $quoteFactory;
        $this->request                  = $request;
        $this->_eventManager            = $eventManager;
        $this->orderRepo                = $orderRepo;
        $this->searchCriteriaBuilder    = $searchCriteriaBuilder;
        $this->orderResourceModel       = $orderResourceModel;
        $this->requestFactory           = $requestFactory;
        $this->httpRequest              = $httpRequest;
        
        parent::__construct($context);
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
        $this->jsonOutput = $this->jsonResultFactory->create();
        $this->jsonOutput->setHttpResponseCode(200);
        
        if (!$this->moduleConfig->isActive()) {
            $this->jsonOutput->setData('DMN Error - Nuvei payment module is not active!');
            return $this->jsonOutput;
        }
        
        try {
            $params = array_merge(
                $this->request->getParams(),
                $this->request->getPostValue()
            );
            
            $this->moduleConfig->createLog($params, 'DMN params:');
            
            ### DEBUG
//            $this->jsonOutput->setData('DMN manually stopped.');
//            $this->moduleConfig->createLog(http_build_query($params), 'DMN params string:');
//            return $this->jsonOutput;
            ### DEBUG
            
            $status = !empty($params['Status']) ? strtolower($params['Status']) : null;
            
            // modify it because of the PayPal Sandbox problem with duplicate Orders IDs
            // we modify it also in Class PaymenAPM getParams().
            if (!empty($params['payment_method']) && 'cc_card' != $params['payment_method']) {
                $params["merchant_unique_id"] = $this->moduleConfig->getClientUniqueId($params["merchant_unique_id"]);
            }
            
            if (!empty($params["order"])) {
                $orderIncrementId = $params["order"];
            } elseif (!empty($params["merchant_unique_id"]) && (int) $params["merchant_unique_id"] != 0) {
                $orderIncrementId = $params["merchant_unique_id"];
            } elseif (!empty($params["orderId"])) {
                $orderIncrementId = $params["orderId"];
            } elseif (!empty($params['dmnType'])
                && in_array($params['dmnType'], ['subscriptionPayment', 'subscription'])
                && !empty($params['clientRequestId'])
                && false!== strpos($params['clientRequestId'], '_')
            ) {
                $orderIncrementId       = 0;
                $clientRequestId_arr    = explode('_', $params["clientRequestId"]);
                
                if (!empty($clientRequestId_arr[1]) && is_numeric($clientRequestId_arr[1])) {
                    $orderIncrementId = $clientRequestId_arr[1];
                }
            } else {
                $this->moduleConfig->createLog('DMN error - no Order ID parameter.');
                
                $this->jsonOutput->setData('DMN error - no Order ID parameter.');
                return $this->jsonOutput;
            }
            
            $valid_resp = $this->validateChecksum($params, $orderIncrementId);
            
            if (true !== $valid_resp) {
                $this->jsonOutput->setData($valid_resp);
                return $this->jsonOutput;
            }
            
            /**
             * Try to create the Order.
             * With this call if there are no errors we set:
             *
             * $this->order
             * $this->orderPayment
             */
            $resp = $this->getOrCreateOrder($params, $orderIncrementId);
            
            if (is_string($resp)) {
                $this->jsonOutput->setData($resp);
                return $this->jsonOutput;
            }
            // Try to create the Order END
            
            // set some variables
            $order_status       = '';
            $order_tr_type      = '';
            $last_record        = []; // last transaction data
            // last saved Additional Info for the transaction
            $ord_trans_addit_info = $this->orderPayment->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
            
            $this->moduleConfig->createLog($ord_trans_addit_info, 'DMN $ord_trans_addit_info');
            
            if (empty($ord_trans_addit_info) || !is_array($ord_trans_addit_info)) {
                $ord_trans_addit_info = [];
            } else {
                $last_record    = end($ord_trans_addit_info);
                
                $order_status   = !empty($last_record[Payment::TRANSACTION_STATUS])
                    ? $last_record[Payment::TRANSACTION_STATUS] : '';
                
                $order_tr_type    = !empty($last_record[Payment::TRANSACTION_TYPE])
                    ? $last_record[Payment::TRANSACTION_TYPE] : '';
            }
            
            # prepare current transaction data for save
            $this->curr_trans_info = [
                Payment::TRANSACTION_ID             => '',
                Payment::TRANSACTION_AUTH_CODE      => '',
                Payment::TRANSACTION_STATUS         => '',
                Payment::TRANSACTION_TYPE           => '',
                Payment::TRANSACTION_UPO_ID         => '',
                Payment::TRANSACTION_TOTAL_AMOUN    => '',
                Payment::TRANSACTION_PAYMENT_METHOD => '',
                Payment::SUBSCR_IDS                 => '',
                'start_subscr_data'                 => '',
            ];
            
            // some subscription DMNs does not have TransactionID
            if (isset($params['TransactionID'])) {
                $this->curr_trans_info[Payment::TRANSACTION_ID] = $params['TransactionID'];
            }
            if (isset($params['AuthCode'])) {
                $this->curr_trans_info[Payment::TRANSACTION_AUTH_CODE] = $params['AuthCode'];
            }
            if (isset($params['Status'])) {
                $this->curr_trans_info[Payment::TRANSACTION_STATUS] = $params['Status'];
            }
            if (isset($params['transactionType'])) {
                $this->curr_trans_info[Payment::TRANSACTION_TYPE] = $params['transactionType'];
            }
            if (isset($params['userPaymentOptionId'])) {
                $this->curr_trans_info[Payment::TRANSACTION_UPO_ID] = $params['userPaymentOptionId'];
            }
            if (isset($params['totalAmount'])) {
                $this->curr_trans_info[Payment::TRANSACTION_TOTAL_AMOUN] = $params['totalAmount'];
            }
            if (isset($params['payment_method'])) {
                $this->curr_trans_info[Payment::TRANSACTION_PAYMENT_METHOD] = $params['payment_method'];
            }
            if (!empty($params['customField2'])) {
                $this->curr_trans_info['start_subscr_data'] = $params['customField2'];
            }
            # prepare current transaction data for save END
            
            # check for Subscription State DMN
            $resp = $this->processSubscrDmn($params, $orderIncrementId, $ord_trans_addit_info);
            
            if (is_string($resp)) {
                $this->jsonOutput->setData($resp);
                return $this->jsonOutput;
            }
            # check for Subscription State DMN END
            
            if (empty($params['transactionType'])) {
                $this->moduleConfig->createLog('DMN error - missing Transaction Type.');
                
                $this->jsonOutput->setData('DMN error - missing Transaction Type.');
                return $this->jsonOutput;
            }
            
            if (empty($params['TransactionID'])) {
                $this->moduleConfig->createLog('DMN error - missing Transaction ID.');
                
                $this->jsonOutput->setData('DMN error - missing Transaction ID.');
                return $this->jsonOutput;
            }
            
            $tr_type_param = strtolower($params['transactionType']);

            # Subscription transaction DMN
            if (!empty($params['dmnType'])
                && 'subscriptionPayment' == $params['dmnType']
                && !empty($params['TransactionID'])
            ) {
                $this->order->addStatusHistoryComment(
                    __('<b>Subscription Payment</b> with Status ') . $params['Status']
                        . __(' was made. Plan ID: ') . $params['planId']
                        . __(', Subscription ID: ') . $params['subscriptionId']
                        . __(', Amount: ') . $params['totalAmount'] . ' '
                        . $params['currency'] . __(', TransactionId: ') . $params['TransactionID']
                );
                
                $this->orderResourceModel->save($this->order);

                $this->moduleConfig->createLog('DMN process end for order #' . $orderIncrementId);
                $this->jsonOutput->setData('DMN process end for order #' . $orderIncrementId);

                return $this->jsonOutput;
            }
            # Subscription transaction DMN END
            
            # do not overwrite Order status
            // default - same transaction type, order was approved, but DMN status is different
            if (strtolower($order_tr_type) == $tr_type_param
                && strtolower($order_status) == 'approved'
                && $order_status != $params['Status']
            ) {
                $msg = 'Current Order status is "'. $order_status .'", but incoming DMN status is "'
                    . $params['Status'] . '", for Transaction type '. $order_tr_type
                    .'. Do not apply DMN data on the Order!';
                
                $this->moduleConfig->createLog($msg);
                $this->jsonOutput->setData($msg);
                
                return $this->jsonOutput;
            }
            
            /**
             * When all is same for Sale
             * we do this check only for sale, because Settle, Reffund and Void
             * can be partial
             */
            if (strtolower($order_tr_type) == $tr_type_param
                && $tr_type_param == 'sale'
                && strtolower($order_status) == 'approved'
                && $order_status == $params['Status']
            ) {
                $msg = 'Duplicated Sale DMN. Stop DMN process!';
                
                $this->moduleConfig->createLog($msg);
                $this->jsonOutput->setData($msg);
                
                return $this->jsonOutput;
            }
            
            // do not override status if the Order is Voided or Refunded
            if ('void' == strtolower($order_tr_type)
                && strtolower($order_status) == 'approved'
                && (strtolower($params['transactionType']) != 'void'
                    || 'approved' != $status)
            ) {
                $msg = 'No more actions are allowed for order #' . $this->order->getId();
                
                $this->moduleConfig->createLog($msg);
                $this->jsonOutput->setData($msg);
                
                return $this->jsonOutput;
            }
            
            if (in_array(strtolower($order_tr_type), ['refund', 'credit'])
                && strtolower($order_status) == 'approved'
                && !in_array(strtolower($params['transactionType']), ['refund', 'credit'])
            ) {
                $msg = 'No more actions are allowed for order #' . $this->order->getId();
                
                $this->moduleConfig->createLog($msg);
                $this->jsonOutput->setData($msg);
                
                return $this->jsonOutput;
            }
            
            if ($tr_type_param === 'auth' && strtolower($order_tr_type) === 'settle') {
                $msg = 'Can not set Auth to Settled Order #' . $this->order->getId();
                
                $this->moduleConfig->createLog($msg);
                $this->jsonOutput->setData($msg);
                
                return $this->jsonOutput;
            }
            # do not overwrite Order status END

            $parent_trans_id = isset($params['relatedTransactionId'])
                ? $params['relatedTransactionId'] : null;
            
            $this->orderPayment
                ->setTransactionId($params['TransactionID'])
                ->setParentTransactionId($parent_trans_id)
                ->setAuthCode($params['AuthCode']);
            
            if (!empty($params['payment_method'])) {
                $this->orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_PAYMENT_METHOD,
                    $params['payment_method']
                );
            }
            
            if ($status === "pending") {
                $this->order
                    ->setState(Order::STATE_NEW)
                    ->setStatus('pending');
            }
            
            // compare them later
            $order_total    = round((float) $this->order->getBaseGrandTotal(), 2);
            $dmn_total      = round((float) $params['totalAmount'], 2);
            
            // APPROVED TRANSACTION
            if (in_array($status, ['approved', 'success'])) {
                $message = $this
                    ->captureCommand
                    ->execute($this->orderPayment, $this->order->getBaseGrandTotal(), $this->order);
                
                $this->sc_transaction_type  = Payment::SC_PROCESSING;
                $refund_msg                 = '';
                
                // AUTH
                if ($tr_type_param == 'auth') {
                    $this->processAuthDmn($params, $order_total, $dmn_total, $message);
                } elseif (in_array($tr_type_param, ['sale', 'settle'])
                    && !isset($params['dmnType'])
                ) { // SALE and SETTLE
                    $this->processSaleAndSettleDMN(
                        $params,
                        $order_total,
                        $dmn_total,
                        $tr_type_param,
                        $last_record
                    );
                } elseif ('void' ==  $tr_type_param) { // VOID
                    $this->transactionType        = Transaction::TYPE_VOID;
                    $this->sc_transaction_type    = Payment::SC_VOIDED;
                    
                    // set the Canceld Invoice
                    $this->curr_trans_info['invoice_id'] = $this->httpRequest->getParam('invoice_id');
                    
                    // mark the Order Invoice as Canceld
                    $invCollection = $this->order->getInvoiceCollection();
                    
                    $this->moduleConfig->createLog(
                        [
                            'invoice_id'        => $this->curr_trans_info['invoice_id'],
                            '$invCollection'    => count($invCollection)
                        ],
                        'Void DMN data:'
                    );
                    
                    if (!empty($invCollection)) {
                        foreach ($invCollection as $invoice) {
                            $this->moduleConfig->createLog($invoice->getId(), 'Invoice');
                            
                            if ($invoice->getId() == $this->curr_trans_info['invoice_id']) {
                                $this->moduleConfig->createLog($invoice->getId(), 'Invoice to be Canceld');
                                
                                $invoice->setState(Invoice::STATE_CANCELED);
                                $this->invoiceRepository->save($invoice);

                                break;
                            }
                        }
                    }
                    // mark the Order Invoice as Canceld END
                    
                    // Cancel active Subscriptions, if there are any
                    $this->cancelSubscription($last_record);
                    
                    $this->order->setData('state', Order::STATE_CLOSED);
                } elseif (in_array($tr_type_param, ['credit', 'refund'])) { // REFUND / CREDIT
                    $this->transactionType        = Transaction::TYPE_REFUND;
                    $this->sc_transaction_type    = Payment::SC_REFUNDED;
                    
                    if ((!empty($params['totalAmount']) && 'cc_card' == $params["payment_method"])
                        || false !== strpos($params["merchant_unique_id"], 'gwp')
                    ) {
                        $refund_msg = '<br/>The Refunded amount is <b>'
                            . $params['totalAmount'] . ' ' . $params['currency'] . '</b>.';
                    }
                    
                    $this->curr_trans_info['invoice_id'] = $this->httpRequest->getParam('invoice_id');
                    
                    $this->order->setData('state', Order::STATE_PROCESSING);
                }
                
                $this->order->setStatus($this->sc_transaction_type);

                $msg_transaction = '<b>'
                    . ($this->is_partial_settle === true ? 'Partial ' : '')
                    . $params['transactionType'] . '</b> ';

                $this->order->addStatusHistoryComment(
                    $msg_transaction
                    . __("request, response status is") . ' <b>' . $params['Status'] . '</b>.<br/>'
                    . __('Transaction ID: ') . $params['TransactionID'] .', '
                    . __('Related Transaction ID: ') . $params['relatedTransactionId'] .', '
                    . __('Transaction Amount: ') . number_format($params['totalAmount'], 2, '.', '')
                    . ' ' . $params['currency'] . ' <br/>' . $refund_msg,
                    $this->sc_transaction_type
                );
            } elseif (in_array($status, ['declined', 'error'])) { // DECLINED/ERROR TRANSACTION
                $this->processDeclinedSaleOrSettleDmn($params);
                
                $params['ErrCode']      = (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
                $params['ExErrCode']    = (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
                
                $this->order->addStatusHistoryComment(
                    '<b>' . $params['transactionType'] . '</b> '
                    . __("request, response status is") . ' <b>' . $params['Status'] . '</b>.<br/>('
                    . __('Code: ') . $params['ErrCode'] . ', '
                    . __('Reason: ') . $params['ExErrCode'] . '.'
                );
            } else { // UNKNOWN DMN
                $this->moduleConfig->createLog('DMN for Order #' . $orderIncrementId . ' was not recognized.');
                $this->jsonOutput->setData('DMN for Order #' . $orderIncrementId . ' was not recognized.');
            }
            
            $ord_trans_addit_info[] = $this->curr_trans_info;
            
            $this->orderPayment
                ->setAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA, $ord_trans_addit_info)
                ->save();
            
            $this->orderResourceModel->save($this->order);
            
            $this->moduleConfig->createLog('DMN process end for order #' . $orderIncrementId);
            $this->jsonOutput->setData('DMN process end for order #' . $orderIncrementId);
            
            # try to create Subscription plans
            $resp = $this->createSubscription($params, $last_record, $orderIncrementId);
        } catch (Exception $e) {
            $msg = $e->getMessage();

            $this->moduleConfig->createLog(
                $msg . "\n\r" . $e->getTraceAsString(),
                'DMN Excception:'
            );

            $this->jsonOutput->setData('Error: ' . $msg);
            $this->order->addStatusHistoryComment($msg);
        }

        return $this->jsonOutput;
    }
    
    /**
     * @param array $params
     * @param float $order_total
     * @param float $dmn_total
     */
    private function processAuthDmn($params, $order_total, $dmn_total)
    {
        $this->sc_transaction_type = Payment::SC_AUTH;

        // amount check
        if ($order_total != $dmn_total) {
            $this->sc_transaction_type = 'fraud';

            $this->order->addStatusHistoryComment(
                __('<b>Attention!</b> - There is a problem with the Order. The Order amount is ')
                    . $this->order->getOrderCurrencyCode() . ' '
                    . $order_total . ', ' . __('but the Authorized amount is ')
                    . $params['currency'] . ' ' . $dmn_total,
                $this->sc_transaction_type
            );
        }

        $this->orderPayment
            ->setAuthAmount($params['totalAmount'])
            ->setIsTransactionPending(true)
            ->setIsTransactionClosed(0);

        // set transaction
        $transaction = $this->transObj->setPayment($this->orderPayment)
            ->setOrder($this->order)
            ->setTransactionId($params['TransactionID'])
            ->setFailSafe(true)
            ->build(Transaction::TYPE_AUTH);

        $transaction->save();
    }
    
    /**
     * @param array     $params
     * @param float     $order_total
     * @param float     $dmn_total
     * @param string    $tr_type_param
     * @param array     $last_tr_record
     */
    private function processSaleAndSettleDMN($params, $order_total, $dmn_total, $tr_type_param, $last_tr_record)
    {
        $this->moduleConfig->createLog('processSaleAndSettleDMN()');
        
        $this->sc_transaction_type  = Payment::SC_SETTLED;
        $invCollection              = $this->order->getInvoiceCollection();
        $inv_amount                 = round(floatval($this->order->getBaseGrandTotal()), 2);
        $dmn_inv_id                 = $this->httpRequest->getParam('invoice_id');
        $is_cpanel_settle           = false;
        
        if (!empty($params["merchant_unique_id"])
            && $params["merchant_unique_id"] != $params["order"]
//            && (float) $params['totalAmount'] < $inv_amount
        ) {
            $is_cpanel_settle = true;
        }

        // set Start Subscription flag
        if ('sale' == $tr_type_param && !empty($params['customField2'])) {
            $this->start_subscr = true;
        } elseif ('settle' == $tr_type_param
            && !empty($last_tr_record)
            && !empty($last_tr_record['start_subscr_data'])
        ) {
            $this->start_subscr = true;
        }
        // set Start Subscription flag END
        
        if ($params["payment_method"] == 'cc_card') {
            $this->order->setCanVoidPayment(true);
            $this->orderPayment->setCanVoid(true);
        }
        
        // add Partial Settle flag
        if ('settle' == $tr_type_param
            && ($inv_amount - round(floatval($params['totalAmount']), 2) > 0.00)
        ) {
            $this->is_partial_settle = true;
        } elseif ($order_total != $dmn_total) { // amount check for Sale only
            $this->sc_transaction_type = 'fraud';

            $this->order->addStatusHistoryComment(
                __('<b>Attention!</b> - There is a problem with the Order. The Order amount is ')
                . $this->order->getOrderCurrencyCode() . ' '
                . $order_total . ', ' . __('but the Paid amount is ')
                . $params['currency'] . ' ' . $dmn_total,
                $this->sc_transaction_type
            );
        }

        // there are invoices
        if (count($invCollection) > 0 && !$is_cpanel_settle) {
            $this->moduleConfig->createLog('There are Invoices');
            
            foreach ($this->order->getInvoiceCollection() as $invoice) {
                // Settle
                if ($dmn_inv_id == $invoice->getId()) {
                    $this->curr_trans_info['invoice_id'] = $invoice->getId();

                    $this->moduleConfig->createLog([
                        '$dmn_inv_id' => $dmn_inv_id,
                        '$invoice->getId()' => $invoice->getId()
                    ]);
                    
                    $invoice->setCanVoidFlag(true);
                    $invoice
                        ->setTransactionId($params['TransactionID'])
                        ->setState(Invoice::STATE_PAID)
                        ->pay();
                    
                    $this->invoiceRepository->save($invoice);
                    
                    return;
                }
            }
            
            return;
        }
        
        // Force Invoice creation when we have CPanel Partial Settle
        if (!$this->order->canInvoice() && !$is_cpanel_settle) {
            $this->moduleConfig->createLog('We can NOT create invoice.');
            return;
        }
        
        $this->moduleConfig->createLog('There are no Invoices');
        
        // there are not invoices, but we can create
        if (
//            ( $this->order->canInvoice() || $is_cpanel_settle )
//            && (
            (
                'sale' == $tr_type_param // Sale flow
                || ( // APMs flow
                    $params["order"] == $params["merchant_unique_id"]
                    && $params["payment_method"] != 'cc_card'
                )
                || $is_cpanel_settle
            )
        ) {
            $this->moduleConfig->createLog('We can create Invoice');
            
            $this->orderPayment
                ->setIsTransactionPending(0)
                ->setIsTransactionClosed(0);
            
            $invoice = $this->invoiceService->prepareInvoice($this->order);
            $invoice->setCanVoidFlag(true);
            
            $invoice
                ->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE)
                ->setTransactionId($params['TransactionID'])
                ->setState(Invoice::STATE_PAID);
            
            // in case of Cpanel Partial Settle
            if ($is_cpanel_settle && (float) $params['totalAmount'] < $inv_amount) {
                $inv_amount = round((float) $params['totalAmount'], 2);
            }
            
            $invoice
                ->setSubtotal($inv_amount)
                ->setBaseSubtotal($inv_amount)
                ->setBaseGrandTotal($inv_amount)
                ->setGrandTotal($inv_amount);
            
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->pay();
            
            $transactionSave = $this->transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            
            $transactionSave->save();

            $this->curr_trans_info['invoice_id'] = $invoice->getId();

            // set transaction
            $transaction = $this->transObj
                ->setPayment($this->orderPayment)
                ->setOrder($this->order)
                ->setTransactionId($params['TransactionID'])
                ->setFailSafe(true)
                ->build(Transaction::TYPE_CAPTURE);

            $transaction->save();

//            $tr_type    = $this->orderPayment->addTransaction(Transaction::TYPE_CAPTURE);
//            $msg        = $this->orderPayment->prependMessage($message);
//
//            $this->orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
            
            return;
        }
    }
    
    /**
     * @param array $params the DMN parameters
     */
    private function processDeclinedSaleOrSettleDmn($params)
    {
        $invCollection  = $this->order->getInvoiceCollection();
        $dmn_inv_id     = 0;
        
        // there are invoices
        if (count($invCollection) > 0) {
            $this->moduleConfig->createLog(count($invCollection), 'The Invoices count is');

            foreach ($this->order->getInvoiceCollection() as $invoice) {
                // Sale
                if (0 == $dmn_inv_id) {
                    $this->curr_trans_info['invoice_id'][] = $invoice->getId();
                    
                    $invoice
                        ->setTransactionId($params['TransactionID'])
                        ->setState(Invoice::STATE_CANCELED)
                        ->pay()
                        ->save();
                } elseif ($dmn_inv_id == $invoice->getId()) { // Settle
                    $this->curr_trans_info['invoice_id'] = $invoice->getId();

                    $invoice
                        ->setTransactionId($params['TransactionID'])
                        ->setState(Invoice::STATE_CANCELED)
                        ->pay()
                        ->save();
                    
                    break;
                }
            }
        }
    }
    
    /**
     * Work with Subscription status DMN.
     *
     * @param array $params
     * @param int   $orderIncrementId
     * @param array   $ord_trans_addit_info
     *
     * @return bool|string
     */
    private function processSubscrDmn($params, $orderIncrementId, $ord_trans_addit_info)
    {
        if (empty($params['dmnType'])
            || 'subscription' != $params['dmnType']
            || empty($params['subscriptionState'])
        ) {
            return false;
        }
        
        $this->moduleConfig->createLog('processSubscrDmn()');
        
        if ('active' == strtolower($params['subscriptionState'])) {
            $this->order->addStatusHistoryComment(
                __("<b>Subscription</b> is Active. Subscription ID: ") . $params['subscriptionId']. ', '
                    . __('Plan ID: ') . $params['planId']. ', '
            );

            // Save the Subscription ID
            foreach ($ord_trans_addit_info as $key => $data) {
                if (!in_array(strtolower($data['transaction_type']), ['sale', 'settle'])) {
                    $this->moduleConfig->createLog($data['transaction_type'], 'processSubscrDmn() active continue');
                    continue;
                }

                $subsc_ids = json_decode($data[Payment::SUBSCR_IDS]);
                
                if (empty($subsc_ids)) {
                    $subsc_ids = [];
                } elseif (in_array($params['subscriptionId'], $subsc_ids)) {
                    continue;
                }

                $subsc_ids[]                                        = $params['subscriptionId'];
                $ord_trans_addit_info[$key][Payment::SUBSCR_IDS]    = json_encode($subsc_ids);
                
                $this->orderPayment->setAdditionalInformation(
                    Payment::ORDER_TRANSACTIONS_DATA,
                    $ord_trans_addit_info
                );
                break;
            }
        }
        
        if ('inactive' == strtolower($params['subscriptionState'])) {
            $subscr_msg = __('<b>Subscription</b> is Inactive. ');

            if (!empty($params['subscriptionId'])) {
                $subscr_msg .= __('Subscription ID: ') . $params['subscriptionId'];
            }

            if (!empty($params['subscriptionId'])) {
                $subscr_msg .= __(', Plan ID: ') . $params['planId'];
            }

            $this->order->addStatusHistoryComment($subscr_msg);
        }
        
        if ('canceled' == strtolower($params['subscriptionState'])) {
            $this->order->addStatusHistoryComment(
                __('<b>Subscription</b> was canceled. ') . '<br/>'
                . __('<b>Subscription ID:</b> ') . $params['subscriptionId']
            );
        }

        $this->orderPayment->save();
        $this->orderResourceModel->save($this->order);

        return 'Process Subscr DMN ends for order #' . $orderIncrementId;
    }

    /**
     * Place order.
     *
     * @param array $params
     */
    private function placeOrder($params)
    {
        $this->moduleConfig->createLog($params['quote'], 'PlaceOrder() quote');
        
        $result = $this->dataObjectFactory->create();
        
        if (empty($params['quote'])) {
            return $result
                ->setData('error', true)
                ->setData('message', 'Missing Quote parameter.');
        }
        
        try {
            $quote = $this->quoteFactory->create()->loadByIdWithoutStore((int) $params['quote']);
            
            if (!is_object($quote)) {
                $this->moduleConfig->createLog($quote, 'placeOrder error - the quote is not an object.');

                return $result
                    ->setData('error', true)
                    ->setData('message', 'The quote is not an object.');
            }
            
            $method = $quote->getPayment()->getMethod();
            
            $this->moduleConfig->createLog(
                [
                    'quote payment Method'  => $method,
                    'quote id'              => $quote->getEntityId(),
                    'quote is active'       => $quote->getIsActive(),
                    'quote reserved ord id' => $quote->getReservedOrderId(),
                ],
                'Quote data'
            );

            if ((int) $quote->getIsActive() == 0) {
                $this->moduleConfig->createLog($quote->getQuoteId(), 'Quote ID');

                return $result
                    ->setData('error', true)
                    ->setData('message', 'Quote is not active.');
            }

            if ($method !== Payment::METHOD_CODE) {
                return $result
                    ->setData('error', true)
                    ->setData('message', 'Quote payment method is "' . $method . '"');
            }

//            $params = array_merge(
//                $this->request->getParams(),
//                $this->request->getPostValue()
//            );
            
            $orderId = $this->cartManagement->placeOrder($params);

            $result
                ->setData('success', true)
                ->setData('order_id', $orderId);

            $this->_eventManager->dispatch(
                'nuvei_place_order',
                [
                    'result' => $result,
                    'action' => $this,
                ]
            );
        } catch (Exception $exception) {
            $this->moduleConfig->createLog($exception->getMessage(), 'DMN placeOrder Exception: ');
            
            return $result
                ->setData('error', true)
                ->setData('message', $exception->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Function validateChecksum
     *
     * @param array $params
     * @param string $orderIncrementId
     *
     * @return mixed
     */
    private function validateChecksum($params, $orderIncrementId)
    {
        if (empty($params["advanceResponseChecksum"]) && empty($params['responsechecksum'])) {
            $msg = 'Required keys advanceResponseChecksum and responsechecksum for checksum calculation are missing.';
            
            $this->moduleConfig->createLog($msg);
            return $msg;
        }
        
        // most of the DMNs with advanceResponseChecksum
        if (!empty($params["advanceResponseChecksum"])) {
            $concat     = $this->moduleConfig->getMerchantSecretKey();
            $params_arr = ['totalAmount', 'currency', 'responseTimeStamp', 'PPP_TransactionID', 'Status', 'productId'];

            foreach ($params_arr as $checksumKey) {
                if (!isset($params[$checksumKey])) {
                    $msg = 'Required key '. $checksumKey .' for checksum calculation is missing.';
                    
                    $this->moduleConfig->createLog($msg);
                    return $msg;
                }

                if (is_array($params[$checksumKey])) {
                    foreach ($params[$checksumKey] as $subVal) {
                        $concat .= $subVal;
                    }
                } else {
                    $concat .= $params[$checksumKey];
                }
            }

            $checksum = hash($this->moduleConfig->getHash(), utf8_encode($concat));

            if ($params["advanceResponseChecksum"] !== $checksum) {
                $msg = 'Checksum validation failed for advanceResponseChecksum and Order #' . $orderIncrementId;

                $this->moduleConfig->createLog($msg);
                return $msg;
            }

            return true;
        }
        
        // subscription DMN with responsechecksum
        $concat = '';
        
        foreach ($params as $name => $value) {
            if ('responsechecksum' == $name) {
                continue;
            }
            
            $concat .= $value;
        }
        
        if (empty($concat)) {
            $msg = 'Checksum string before hash is empty for Order #' . $orderIncrementId;

            $this->moduleConfig->createLog($msg);
            return $msg;
        }
        
        $concat_final = $concat . $this->moduleConfig->getMerchantSecretKey();
        
        $checksum = hash($this->moduleConfig->getHash(), utf8_encode($concat_final));

        if ($params["responsechecksum"] !== $checksum) {
            $msg = 'Checksum validation failed for responsechecksum and Order #' . $orderIncrementId;

            $this->moduleConfig->createLog([$concat, $checksum], $msg);
            return $msg;
        }
        
        return true;
    }
    
    /**
     * Try to create Subscriptions.
     *
     * @param array $params
     * @param array $last_record
     * @param int   $orderIncrementId
     *
     * @return bool
     */
    
    private function createSubscription($params, $last_record, $orderIncrementId)
    {
        $this->moduleConfig->createLog($this->start_subscr, 'start_subscr');
        
        // no need to create a Subscription
        if (!$this->start_subscr) {
            return false;
        }
            
        $customField2   = json_decode($params['customField2'], true);
        $customField5   = json_decode($params['customField5'], true);
        $subsc_data     = [];
        $subscr_count   = 0;

        // we allow only one Product in the Order to be with Payment Plan,
        // so the list with the products must be with length = 1
        if (!empty($customField2) && is_array($customField2)) {
            $subsc_data = current($customField2);
        } elseif (!empty($last_record[Payment::TRANSACTION_UPO_ID])
            && is_numeric($last_record[Payment::TRANSACTION_UPO_ID])
        ) {
            $subsc_data = current($last_record['start_subscr_data']);
        }

        // we create as many Subscriptions as the Product quantity is
        if (!empty($customField5) && is_array($customField5)) {
            $customField5_curr = current($customField5);

            if (isset($customField5_curr['quantity']) && is_numeric($customField5_curr['quantity'])) {
                $subscr_count = (int) $customField5_curr['quantity'];
            }
        } else {
            $items = $this->order->getAllItems();

            foreach ($items as $item) {
                $subscr_count += $item->getQtyOrdered();
            }
        }

        // Error - missing Subscription details
        if (empty($subsc_data) || 0 == $subscr_count) {
            $this->moduleConfig->createLog(
                [
                    'subsc_data'    => $subsc_data,
                    'subscr_count'  => $subscr_count,
                ],
                'DMN Error - can not create Subscription beacuse of missing data:'
            );
            
            return false;
        }
        
        // create subscriptions for each of the Products
        $request = $this->requestFactory->create(AbstractRequest::CREATE_SUBSCRIPTION_METHOD);
        
        do {
            $subsc_data['userPaymentOptionId'] = $params['userPaymentOptionId'];
            $subsc_data['userTokenId']         = $params['email'];
            $subsc_data['currency']            = $params['currency'];
            
            try {
                $params = array_merge(
                    $this->request->getParams(),
                    $this->request->getPostValue()
                );

                $resp = $request
                    ->setOrderId($orderIncrementId)
                    ->setData($subsc_data)
                    ->process();

                // add note to the Order - Success
                if ('success' == strtolower($resp['status'])) {
                    $msg =  __("<b>Subscription</b> was created. Subscription ID "
                        . $resp['subscriptionId']). '. '
                        . __('Recurring amount: ') . $params['currency'] . ' '
                        . $subsc_data['recurringAmount'];
                } else { // Error, Decline
                    $msg = __("<b>Error</b> when try to create Subscription by this Order. ");

                    if (!empty($resp['reason'])) {
                        $msg .= '<br/>' . __('Reason: ') . $resp['reason'];
                    }
                }

                $this->order->addStatusHistoryComment($msg, $this->sc_transaction_type);
                $this->orderResourceModel->save($this->order);
            } catch (PaymentException $e) {
                $this->moduleConfig->createLog('createSubscription - Error: ' . $e->getMessage());
            }
            
            $subscr_count--;
        } while ($subscr_count > 0);
        
        return true;
    }
    
    /**
     * Cancel a Subscription.
     *
     * @param array $last_record
     * @return bool
     */
    private function cancelSubscription($last_record)
    {
        $this->moduleConfig->createLog('cancelSubscription()');
        
        $subsc_ids = json_decode($last_record[Payment::SUBSCR_IDS]);

        if (empty($subsc_ids) || !is_array($subsc_ids)) {
            $this->moduleConfig->createLog(
                $subsc_ids,
                'cancelSubscription() Error - $subsc_ids is empty or not an array.'
            );
            return false;
        }

        $request    = $this->requestFactory->create(AbstractRequest::CANCEL_SUBSCRIPTION_METHOD);
        $msg        = '';

        foreach ($subsc_ids as $id) {
            $resp = $request
                ->setSubscrId($id)
                ->process();

            // add note to the Order - Success
            if (!$resp || !is_array($resp) || 'SUCCESS' != $resp['status']) {
                $msg = __("<b>Error</b> when try to create Subscription by this Order. ");

                if (!empty($resp['reason'])) {
                    $msg .= '<br/>' . __('Reason: ') . $resp['reason'];
                }
            }

            $this->order->addStatusHistoryComment($msg, $this->sc_transaction_type);
            $this->orderResourceModel->save($this->order);
        }
        
        return true;
    }
    
    private function getOrCreateOrder($params, $orderIncrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $orderIncrementId, 'eq')->create();

        $tryouts    = 0;
        $max_tries  = 5;
        
        // search only once for Refund/Credit
        if (isset($params['transactionType'])
            && in_array(strtolower($params['transactionType']), ['refund', 'credit'])
        ) {
            $max_tries = 0;
        }
        
        // do not search more than once for Auth and Sale, if the DMN response time is more than 24 hours before now
        if ($max_tries > 0
            && isset($params['transactionType'])
            && in_array(strtolower($params['transactionType']), ['sale', 'auth'])
            && !empty($params['customField4'])
            && is_numeric($params['customField4'])
            && time() - $params['customField4'] > 3600
        ) {
            $max_tries = 0;
        }
        
        do {
            $tryouts++;
            $orderList = $this->orderRepo->getList($searchCriteria)->getItems();

            if (!$orderList || empty($orderList)) {
                $this->moduleConfig->createLog('DMN try ' . $tryouts
                    . ' there is NO order for TransactionID ' . $params['TransactionID'] . ' yet.');
                sleep(3);
            }
        } while ($tryouts < $max_tries && empty($orderList));
        
        // try to create the order
        if ( (!$orderList || empty($orderList))
            && !isset($params['dmnType'])
        ) {
            if (in_array(strtolower($params['transactionType']), ['sale', 'auth'])
                && strtolower($params['Status']) != 'approved'
            ) {
                $this->moduleConfig->createLog('The Order '. $orderIncrementId .' is not approved, stop process.');
                
                return 'getOrCreateOrder() error - The Order ' . $orderIncrementId .' is not approved, stop process.';
            }
            
            $this->moduleConfig->createLog('Order '. $orderIncrementId .' not found, try to create it!');

            $result = $this->placeOrder($params);

            if ($result->getSuccess() !== true) {
                $this->moduleConfig->createLog('DMN Callback error - place order error: ' . $result->getMessage());

                return 'DMN Callback error - place order error: ' . $result->getMessage();
            }

            $orderList = $this->orderRepo->getList($searchCriteria)->getItems();

            $this->moduleConfig->createLog('An Order with ID '. $orderIncrementId .' was created in the DMN page.');
        }
        
        if (!$orderList || empty($orderList)) {
            $this->moduleConfig->createLog(
                'DMN Callback error - there is no Order and the code did not success to create it.'
            );
            
            http_response_code(400);
            return 'DMN Callback error - there is no Order and the code did not success to create it.';
        }
        
        $this->order = current($orderList);
        
        if (null === $this->order) {
            $this->moduleConfig->createLog($orderList, 'DMN error - Order object is null.');

            return 'DMN error - Order object is null.';
        }
        
        $this->orderPayment = $this->order->getPayment();
        
        if (null === $this->orderPayment) {
            $this->moduleConfig->createLog('DMN error - Order Payment object is null.');

            return 'DMN error - Order Payment object is null.';
        }
        
        // check if the Order belongs to nuvei
        $method = $this->orderPayment->getMethod();

        if ('nuvei' != $method) {
            $this->moduleConfig->createLog(
                [
                    'orderIncrementId' => $orderIncrementId,
                    'module' => $method,
                ],
                'DMN getOrCreateOrder() error - the order does was not made with Nuvei module.'
            );

            return 'DMN getOrCreateOrder() error - the order does was not made with Nuvei module.';
        }
    }
}
