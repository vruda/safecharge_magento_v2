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
        $jsonOutput = $this->jsonResultFactory->create();
        $jsonOutput->setHttpResponseCode(200);
        
        if (!$this->moduleConfig->isActive()) {
            $jsonOutput->setData('DMN Error - Nuvei payment module is not active!');
            return $jsonOutput;
        }
        
        try {
            $params = array_merge(
                $this->request->getParams(),
                $this->request->getPostValue()
            );
            
            $this->moduleConfig->createLog($params, 'DMN params:');
            
            ### DEBUG
//            $jsonOutput->setData('DMN manually stopped.');
//            $this->moduleConfig->createLog(http_build_query($params), 'DMN params string:');
//            return $jsonOutput;
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
                
                $jsonOutput->setData('DMN error - no Order ID parameter.');
                return $jsonOutput;
            }
            
            $valid_resp = $this->validateChecksum($params, $orderIncrementId);
            
            if (true !== $valid_resp) {
                $jsonOutput->setData($valid_resp);
                return $jsonOutput;
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
            if (isset($params['customField2'])) {
                $this->curr_trans_info['start_subscr_data'] = $params['customField2'];
            }
            # prepare current transaction data for save END
            
            # in case this is Subscription confirm DMN
            if (!empty($params['dmnType']) && 'subscription' == $params['dmnType']) {
                $this->getOrCreateOrder($params, $orderIncrementId, $jsonOutput);

                $this->orderPayment = $this->order->getPayment();

                if (empty($this->orderPayment)) {
                    $this->moduleConfig->createLog('Order Payment data is empty for order #' . $orderIncrementId);
                    $jsonOutput->setData('Order Payment data is empty for order #' . $orderIncrementId);

                    return $jsonOutput;
                }

                if (!empty($params['subscriptionState'])) {
                    if ('active' == strtolower($params['subscriptionState'])) {
                        $this->order->addStatusHistoryComment(
                            __("<b>Subscription</b> is Active. Subscription ID: ") . $params['subscriptionId']. ', '
                                . __('Plan ID: ') . $params['planId']. ', '
                        );

                        $this->orderPayment->setAdditionalInformation(Payment::IS_ACTIVE_SUBS_ORDER, 1);
                        $this->orderPayment->save();
                        
                    } elseif ('inactive' == strtolower($params['subscriptionState'])) {
                        $subscr_msg = __('Subscription is Inactive. ');
                        
                        if (!empty($params['subscriptionId'])) {
                            $subscr_msg .= __('Subscription ID: ') . $params['subscriptionId'];
                        }
                        
                        if (!empty($params['subscriptionId'])) {
                            $subscr_msg .= __(', Plan ID: ') . $params['planId'];
                        }
                        
                        $this->order->addStatusHistoryComment($subscr_msg);
                    }
                }

                $this->orderPayment->save();
                $this->orderResourceModel->save($this->order);

                $this->moduleConfig->createLog('DMN process end for order #' . $orderIncrementId);
                $jsonOutput->setData('DMN process end for order #' . $orderIncrementId);

                return $jsonOutput;
            }
            # in case this is Subscription confirm DMN END
            
            if (empty($params['transactionType'])) {
                $this->moduleConfig->createLog('DMN error - missing Transaction Type.');
                
                $jsonOutput->setData('DMN error - missing Transaction Type.');
                return $jsonOutput;
            }
            
            if (empty($params['TransactionID'])) {
                $this->moduleConfig->createLog('DMN error - missing Transaction ID.');
                
                $jsonOutput->setData('DMN error - missing Transaction ID.');
                return $jsonOutput;
            }

            # try to create the order
            $this->getOrCreateOrder($params, $orderIncrementId, $jsonOutput);
            
            if (null === $this->order) {
                $this->moduleConfig->createLog('DMN error - Order object is null.');
                
                $jsonOutput->setData('DMN error - Order object is null.');
                return $jsonOutput;
            }
            
            $this->orderPayment = $this->order->getPayment();
            $order_status       = '';
            $order_tr_type      = '';
            $last_record        = []; // from Payment::ORDER_TRANSACTIONS_DATA
            
            // add data to the Payment
            // the new structure of the data
            $ord_trans_addit_info = $this->orderPayment
                ->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
            
            $this->moduleConfig->createLog($ord_trans_addit_info, '$ord_trans_addit_info');
            
            if (empty($ord_trans_addit_info) || !is_array($ord_trans_addit_info)) {
                $ord_trans_addit_info = [];
            } else {
                $last_record    = end($ord_trans_addit_info);
                
                $order_status   = !empty($last_record[Payment::TRANSACTION_STATUS])
                    ? $last_record[Payment::TRANSACTION_STATUS] : '';
                
                $order_tr_type    = !empty($last_record[Payment::TRANSACTION_TYPE])
                    ? $last_record[Payment::TRANSACTION_TYPE] : '';
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
                $jsonOutput->setData('DMN process end for order #' . $orderIncrementId);

                return $jsonOutput;
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
                $jsonOutput->setData($msg);
                
                return $jsonOutput;
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
                $jsonOutput->setData($msg);
                
                return $jsonOutput;
            }
            
            // do not override status if the Order is Voided or Refunded
            if ('void' == strtolower($order_tr_type)
                && strtolower($order_status) == 'approved'
                && (strtolower($params['transactionType']) != 'void'
                    || 'approved' != $status)
            ) {
                $msg = 'No more actions are allowed for order #' . $this->order->getId();
                
                $this->moduleConfig->createLog($msg);
                $jsonOutput->setData($msg);
                
                return $jsonOutput;
            }
            
            if (in_array(strtolower($order_tr_type), ['refund', 'credit'])
                && strtolower($order_status) == 'approved'
                && !in_array(strtolower($params['transactionType']), ['refund', 'credit'])
            ) {
                $msg = 'No more actions are allowed for order #' . $this->order->getId();
                
                $this->moduleConfig->createLog($msg);
                $jsonOutput->setData($msg);
                
                return $jsonOutput;
            }
            
            if ($tr_type_param === 'auth' && strtolower($order_tr_type) === 'settle') {
                $msg = 'Can not set Auth to Settled Order #' . $this->order->getId();
                
                $this->moduleConfig->createLog($msg);
                $jsonOutput->setData($msg);
                
                return $jsonOutput;
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
                } elseif (in_array($tr_type_param, ['void', 'voidcredit'])) { // VOID
                    $this->transactionType        = Transaction::TYPE_VOID;
                    $this->sc_transaction_type    = Payment::SC_VOIDED;
                    
                    // set the Canceld Invoice
                    $this->curr_trans_info['invoice_id'] = $this->httpRequest->getParam('invoice_id');
                    
                    // mark the Order Invoice as Canceld
                    $invCollection = $this->order->getInvoiceCollection();
                    
                    $this->moduleConfig->createLog($this->curr_trans_info['invoice_id'], 'invoice_id');
                    $this->moduleConfig->createLog(count($invCollection), '$invCollection');
                    
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
                $this->processDeclinedSaleOrSettleDmn();
                
                $params['ErrCode']        = (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
                $params['ExErrCode']    = (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
                
                $this->order->addStatusHistoryComment(
                    '<b>' . $params['transactionType'] . '</b> '
                    . __("request, response status is") . ' <b>' . $params['Status'] . '</b>.<br/>('
                    . __('Code: ') . $params['ErrCode'] . ', '
                    . __('Reason: ') . $params['ExErrCode'] . '.'
                );
            } else { // UNKNOWN DMN
                $this->moduleConfig->createLog('DMN for Order #' . $orderIncrementId . ' was not recognized.');
                $jsonOutput->setData('DMN for Order #' . $orderIncrementId . ' was not recognized.');
            }
            
            $ord_trans_addit_info[] = $this->curr_trans_info;
            
            $this->orderPayment
                ->setAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA, $ord_trans_addit_info)
                ->save();
            
            $this->orderResourceModel->save($this->order);
            
            $this->moduleConfig->createLog('DMN process end for order #' . $orderIncrementId);
            $jsonOutput->setData('DMN process end for order #' . $orderIncrementId);
            
            # start Subscription plans if we need to
            $this->moduleConfig->createLog($this->start_subscr, '$this->start_subscr');
            
            if ($this->start_subscr) {
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
                
                if (!empty($subsc_data) && $subscr_count > 0) {
                    // create subscriptions for each of the Products
                    do {
                        $subsc_data['userPaymentOptionId'] = $params['userPaymentOptionId'];
                        $subsc_data['userTokenId']         = $params['email'];
                        $subsc_data['currency']            = $params['currency'];
                        
                        $resp = $this->createSubscription($subsc_data, $orderIncrementId);
                        
                        // add note to the Order
                        if ('success' == strtolower($resp['status'])) {
                            $this->order->addStatusHistoryComment(
                                __("<b>Subscription</b> was created. Subscription ID "
                                    . $resp['subscriptionId']). '. '
                                    . __('Recurring amount: ') . $params['currency'] . ' '
                                    . $subsc_data['recurringAmount'],
                                $this->sc_transaction_type
                            );
                        } else {
                            $msg = __("<b>Error</b> when try to create Subscription by this Order. ");

                            if (!empty($resp['reason'])) {
                                $msg .= '<br/>' . __('Reason: ') . $resp['reason'];
                            }

                            $this->order->addStatusHistoryComment($msg, $this->sc_transaction_type);
                        }
                        
                        $this->orderResourceModel->save($this->order);
                        
                        $subscr_count--;
                    } while ($subscr_count > 0);
                }
            }
            # start Subscription plans if we need to END
        } catch (Exception $e) {
            $msg = $e->getMessage();

            $this->moduleConfig->createLog(
                $msg . "\n\r" . $e->getTraceAsString(),
                'DMN Excception:'
            );
            
            $jsonOutput->setData('Error: ' . $msg);
            $this->order->addStatusHistoryComment($msg);
        }

        return $jsonOutput;
    }
    
    /**
     * @param array        $params
     * @param float        $order_total
     * @param float        $dmn_total
     * @param string    $message
     */
    private function processAuthDmn($params, $order_total, $dmn_total, $message)
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

//        $tr_type    = $this->orderPayment->addTransaction(Transaction::TYPE_AUTH);
//        $msg        = $this->orderPayment->prependMessage($message);
//
//        $this->orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
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
    
    private function processDeclinedSaleOrSettleDmn()
    {
        $invCollection  = $this->order->getInvoiceCollection();
        $inv_amount     = round((float) $this->order->getBaseGrandTotal(), 2);
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
            $quote  = $this->quoteFactory->create()->loadByIdWithoutStore((int) $params['quote']);
            $method = $quote->getPayment()->getMethod();
            
            $this->moduleConfig->createLog(
                [
                    'quote Method'  => $method,
                    'quote id'      => $quote->getQuoteId(),
                ],
                '$method'
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

            $params = array_merge(
                $this->request->getParams(),
                $this->request->getPostValue()
            );
            
            $orderId = $this->cartManagement->placeOrder((int) $params['quote']);

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
                    foreach ($params[$checksumKey] as $subKey => $subVal) {
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
     *
     * @param array $subsc_data - one array to rule them all
     * @param int $order_id - this is increment ID !!!
     *
     * @return type
     */
    private function createSubscription($subsc_data, $order_id)
    {
        $result = $this->jsonResultFactory->create()
            ->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);
        
        if (!$this->moduleConfig->isActive()) {
            $this->moduleConfig->createLog('Nuvei payments module is not active at the moment!');
           
            return $result->setData([
                'error_message' => __('Nuvei payments module is not active at the moment!')
            ]);
        }
        
        try {
            $params = array_merge(
                $this->request->getParams(),
                $this->request->getPostValue()
            );
            
            $request = $this->requestFactory->create(AbstractRequest::CREATE_SUBSCRIPTION_METHOD);
            
            $result = $request
                ->setOrderId($order_id)
                ->setData($subsc_data)
                ->process();
            
            return $result;
        } catch (PaymentException $e) {
            $this->moduleConfig->createLog('createSubscription - Error: ' . $e->getMessage());
            
            return $result->setData([
                "status" => 'error',
                'reason' => $e->getMessage()
            ]);
        }
    }
    
    private function getOrCreateOrder($params, $orderIncrementId, $jsonOutput)
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
        if ((!$orderList || empty($orderList))
            && !isset($params['dmnType'])
        ) {
            if (in_array(strtolower($params['transactionType']), ['sale', 'auth'])
                && strtolower($params['Status']) != 'approved'
            ) {
                $this->moduleConfig->createLog('The Order '. $orderIncrementId .' is not approved, stop process.');
                $jsonOutput->setData('getOrCreateOrder() error - The Order '
                    . $orderIncrementId .' is not approved, stop process.');
                
                return $jsonOutput;
            }
            
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
        
        if (!$orderList || empty($orderList)) {
            $this->moduleConfig->createLog(
                'DMN Callback error - there is no Order and the code did not success to made it.'
            );
            
            $jsonOutput->setData('DMN Callback error - there is no Order and the code did not success to made it.');
            return $jsonOutput;
        }
        
        $this->order = current($orderList);
        
        // check if the Order belongs to nuvei
        try {
            $method = $this->order->getPayment()->getMethod();
            
            if ('nuvei' != $method) {
                $this->moduleConfig->createLog(
                    [
                        'orderIncrementId' => $orderIncrementId,
                        'module' => $method,
                    ],
                    'DMN getOrCreateOrder() error - the order does was not made with Nuvei module.'
                );

                $jsonOutput->setData('DMN getOrCreateOrder() error - the order does was not made with Nuvei module.');
                return $jsonOutput;
            }
        } catch (Exception $ex) {
            $this->moduleConfig->createLog($ex->getMessage(), 'DMN getOrCreateOrder() Exception');
            
            $jsonOutput->setData('DMN getOrCreateOrder() Exception');
            return $jsonOutput;
        }
    }
}
