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
    private $start_subscr		= false;
    private $is_partial_settle	= false;
	private $curr_trans_info	= []; // collect the info for the current transaction (action)

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
        $this->httpRequest				= $httpRequest;
        
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
                && is_numeric($params['clientRequestId'])
            ) {
                $orderIncrementId = $params["clientRequestId"];
            } else {
                $this->moduleConfig->createLog('DMN error - no Order ID parameter.');
                
                $jsonOutput->setData('DMN error - no Order ID parameter.');
                return $jsonOutput;
            }
            
            $this->validateChecksum($params, $orderIncrementId);
            
            # in case this is Subscription confirm DMN
            /*
            if(!empty($params['dmnType']) && 'subscription' == $params['dmnType']) {
                $this->getOrCreateOrder($params, $orderIncrementId, $jsonOutput);

                $this->orderPayment = $this->order->getPayment();

                if(empty($this->orderPayment)) {
                    $this->moduleConfig->createLog('Order Payment data is empty for order #' . $orderIncrementId);
                    $jsonOutput->setData('Order Payment data is empty for order #' . $orderIncrementId);

                    return $jsonOutput;
                }

                $subs = $this->orderPayment->getAdditionalInformation(Payment::TRANSACTION_SUBS); // array

                if(empty($subs)) {
                    $subs = [];
                }

                $subs[$params['subscriptionId']] = [
                    'planId' => $params['planId'],
                    'subscriptionState' => $params['subscriptionState'],
                ];

                $this->orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_SUBS,
                    $subs
                );

                if(!empty($params['subscriptionState'])) {
                    if('active' == strtolower($params['subscriptionState'])) {
                        $this->order->setData('state', Order::STATE_PROCESSING);
                        $this->order->setStatus(Payment::SC_SUBSCRT_STARTED);
                    }
                    elseif('inactive' == strtolower($params['subscriptionState'])) {
                        $this->order->setData('state', Order::STATE_COMPLETE);
                        $this->order->setStatus(Payment::SC_SUBSCRT_ENDED);
                    }
                }

                $this->orderPayment->save();
                $this->orderResourceModel->save($this->order);

                $this->moduleConfig->createLog('Subscription DMN process end for order #' . $orderIncrementId);
                $jsonOutput->setData('Subscription DMN process end for order #' . $orderIncrementId);

                return $jsonOutput;
            }
             */
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
            
            $this->orderPayment	= $this->order->getPayment();
            $order_status		= '';
            $order_tr_type		= '';
            
            // add data to the Payment
            // the new structure of the data
            $ord_trans_addit_info = $this->orderPayment->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
            
            $this->moduleConfig->createLog($ord_trans_addit_info, '$ord_trans_addit_info');
            
            if (empty($ord_trans_addit_info) || !is_array($ord_trans_addit_info)) {
                $ord_trans_addit_info = [];
            } else {
				$last_record	= end($ord_trans_addit_info);
				
                $order_status   = !empty($last_record[Payment::TRANSACTION_STATUS])
					? $last_record[Payment::TRANSACTION_STATUS] : '';
                
				$order_tr_type	= !empty($last_record[Payment::TRANSACTION_TYPE])
					? $last_record[Payment::TRANSACTION_TYPE] : '';
            }
            
            $tr_type_param = strtolower($params['transactionType']);
            
            # Subscription transaction DMN
            /*
            if(
                !empty($params['dmnType'])
                && 'subscriptionPayment' == $params['dmnType']
                && !empty($params['TransactionID'])
            ) {
                $order->addStatusHistoryComment(
                    __('<b>Subscription Payment</b> with Status ') . $params['Status']
                        . __(' was made, by Plan ID ') . $params['planId']
                        . __(' and Subscription ID ') . $params['subscriptionId']
                        . __(', for Amount ') . $params['totalAmount'] . ' '
                        . $params['currency'] . __(', TransactionId: ') . $params['TransactionID']
                );
                $this->orderResourceModel->save($order);

                $subs_data = $this->orderPayment->getAdditionalInformation('sc_subscriptions');

                if(empty($subs_data)) {
                    $subs_data = [];
                }

                $subs_data[] = [
                    'subscriptionId' => $params['subscriptionId'],
                    'subscriptionState' => $params['subscriptionState'],
                    'planId' => $params['planId'],
                    'templateId' => $params['templateId'],
                    'productName' => $params['productName'],
                    'userPaymentOptionId' => $params['userPaymentOptionId'],
                    'AuthCode' => $params['AuthCode'],
                    'PPP_TransactionID' => $params['PPP_TransactionID'],
                    'orderTransactionId' => $params['orderTransactionId'],
                    'TransactionID' => $params['TransactionID'],
                    'ErrCode' => $params['ErrCode'],
                    'ReasonCode' => $params['ReasonCode'],
                    'transactionType' => $params['transactionType'],
                    'Status' => $params['Status'],
                    'totalAmount' => $params['totalAmount'],
                    'currency' => $params['currency'],
                ];

                $this->orderPayment->setAdditionalInformation('sc_subscriptions', $subs_data);
                $this->orderPayment->save();

                $this->moduleConfig->createLog('Subscription DMN process end for order #' . $orderIncrementId);
                $jsonOutput->setData('Subscription DMN process end for order #' . $orderIncrementId);

                return $jsonOutput;
            }
             */
            # Subscription transaction DMN END
            
            // do not overwrite Order status
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
            
            // do not override status if the Order is Voided or Refunded
            if ('void' == strtolower($order_tr_type)
                && strtolower($order_status) == 'approved'
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
            // do not overwrite Order status END

            $this->curr_trans_info = [
                Payment::TRANSACTION_ID             => $params['TransactionID'],
                Payment::TRANSACTION_AUTH_CODE      => $params['AuthCode'] ?: '',
                Payment::TRANSACTION_PAYMENT_METHOD	=> $params['payment_method'] ?: '',
                Payment::TRANSACTION_STATUS         => $params['Status'] ?: '',
                Payment::TRANSACTION_TYPE           => $params['transactionType'] ?: '',
                Payment::TRANSACTION_UPO_ID         => $params['userPaymentOptionId'] ?: '',
                Payment::TRANSACTION_TOTAL_AMOUN    => $params['totalAmount'] ?: '',
            ];
            // the new structure of the data END
            
            $parent_trans_id = $params['relatedTransactionId'] ?: null;
            
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
            $order_total	= round(floatval($this->order->getBaseGrandTotal()), 2);
            $dmn_total      = round(floatval($params['totalAmount']), 2);
            
            // APPROVED TRANSACTION
            if (in_array($status, ['approved', 'success'])) {
                $message = $this
                    ->captureCommand
                    ->execute($this->orderPayment, $this->order->getBaseGrandTotal(), $this->order);
                
                $this->sc_transaction_type	= Payment::SC_PROCESSING;
//                $is_closed              = false;
                $refund_msg             = '';
                
				// AUTH
                if ($tr_type_param == 'auth') {
					$this->processAuthDmn($params, $order_total, $dmn_total, $message);
                }
				// SALE and SETTLE
				elseif (in_array($tr_type_param, ['sale', 'settle']) && !isset($params['dmnType']) ) {
					$this->processSaleAndSettleDMN($params, $order_total, $dmn_total, $tr_type_param);
                }
				// VOID
				elseif (in_array($tr_type_param, ['void', 'voidcredit'])) {
                    $this->transactionType        = Transaction::TYPE_VOID;
                    $this->sc_transaction_type    = Payment::SC_VOIDED;
                    
                    $this->order->setData('state', Order::STATE_CLOSED);
                }
				// REFUND / CREDIT
				elseif (in_array($tr_type_param, ['credit', 'refund'])) {
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
                
                if ($this->is_partial_settle) {
                    $this->order->addStatusHistoryComment(
                        __("The <b>Partial Settle</b> request for amount of ")
                            . "<b>" . number_format($params['totalAmount'], 2, '.', '') . ' '
                            . $params['currency'] . "</b>, "
                            . __("returned") . " <b>" . $params['Status'] . "</b> "
                            . __("status") . ".<br/>"
                            . __('Transaction ID: ') . $params['TransactionID'] .', '
                            . __('Related Transaction ID: ')
                            . $params['relatedTransactionId'] . $refund_msg,
                        $this->sc_transaction_type
                    );
                } else {
                    $this->order->addStatusHistoryComment(
                        '<b>' . $params['transactionType'] . '</b> '
                        . __("request, response status is") . ' <b>' . $params['Status'] . '</b>.<br/>'
                        . __('Transaction ID: ') . $params['TransactionID'] .', '
                        . __('Related Transaction ID: ') . $params['relatedTransactionId'] . $refund_msg,
                        $this->sc_transaction_type
                    );
                }
            }
			// DECLINED/ERROR TRANSACTION
			elseif (in_array($status, ['declined', 'error'])) {
				$this->processDeclinedSaleOrSettleDmn();
				
                $params['ErrCode']		= (isset($params['ErrCode'])) ? $params['ErrCode'] : "Unknown";
                $params['ExErrCode']	= (isset($params['ExErrCode'])) ? $params['ExErrCode'] : "Unknown";
                
                $this->order->addStatusHistoryComment(
                    '<b>' . $params['transactionType'] . '</b> '
                    . __("request, response status is") . ' <b>' . $params['Status'] . '</b>.<br/>('
                    . __('Code: ') . $params['ErrCode'] . ', '
                    . __('Reason: ') . $params['ExErrCode'] . '.'
                );
            }
			// UNKNOWN DMN
			else {
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
            
            // start Subscription plans if we need to
            /*
            if($this->start_subscr) {
                $customField2    = json_decode($params['customField2'], true); // subscriptions data
                $subs            = $this->orderPayment->getAdditionalInformation(Payment::TRANSACTION_SUBS); // array
                $this->moduleConfig->createLog($subs, 'Saved Order Subscriptions Data');

                if(
                    !empty($customField2)
                    && is_array($customField2)
                    && !empty($params['userPaymentOptionId'])
                    && is_numeric($params['userPaymentOptionId'])
                ) {
                    foreach ($customField2 as $item_id => $subsc_data) {
                        $this->moduleConfig->createLog(
                            [
                                'userPaymentOptionId'    => $params['userPaymentOptionId'],
                                'subscr_details'        => $subsc_data
                            ],
                            'Start subscription'
                        );

                        $subscr_request_data                        = $subsc_data;
                        $subscr_request_data['userPaymentOptionId']    = $params['userPaymentOptionId'];
                        $subscr_request_data['userTokenId']            = $params['email'];
                        $subscr_request_data['clientRequestId']        = $orderIncrementId;
                        $subscr_request_data['currency']            = $params['currency'];

                        $resp = $this->createSubscription($subscr_request_data, $orderIncrementId);

                        // add note to the Order
                        if('success' == strtolower($resp['status'])) {
                            $this->order->addStatusHistoryComment(
                                __("Subscription was created. Subscription ID " . $resp['subscriptionId']),
                                $this->sc_transaction_type
                            );
                        }
                        else {
                            $msg = __("Error when try to create Subscription by this Order.");

                            if(!empty($resp['reason'])) {
                                $msg .= __('Reason: ') . $resp['reason'];
                            }

                            $this->order->addStatusHistoryComment($msg, $this->sc_transaction_type);
                        }
                    }
                }
            }
             */
            // start Subscription plans if we need to END
        } catch (Exception $e) {
            $msg = $e->getMessage();

            $this->moduleConfig->createLog($e->getMessage() . "\n\r" . $e->getTraceAsString(), 'DMN Excception:');
            $jsonOutput->setData('Error: ' . $e->getMessage());
            $this->order->addStatusHistoryComment($msg);
        }

        return $jsonOutput;
    }
	
	/**
	 * @param array		$params
	 * @param float		$order_total
	 * @param float		$dmn_total
	 * @param string	$message
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

//                    if(0 == $params['totalAmount']) {
//                        $this->start_subscr = true;
//                    }

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

		$tr_type    = $this->orderPayment->addTransaction(Transaction::TYPE_AUTH);
		$msg        = $this->orderPayment->prependMessage($message);

		$this->orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
	}
    
	/**
	 * @param array		$params
	 * @param float		$order_total
	 * @param float		$dmn_total
	 * @param string	$tr_type_param
	 */
	private function processSaleAndSettleDMN($params, $order_total, $dmn_total, $tr_type_param)
	{
		$this->sc_transaction_type	= Payment::SC_SETTLED;
		$invCollection				= $this->order->getInvoiceCollection();
		$inv_amount					= round(floatval($this->order->getBaseGrandTotal()), 2);
		$dmn_inv_id					= $this->httpRequest->getParam('invoice_id');

//                    if($params['totalAmount'] > 0) {
//                        $this->start_subscr = true;
//                    }

		// add Partial Settle flag
		if ('settle' == $tr_type_param) {
			if(($inv_amount - round(floatval($params['totalAmount']), 2) > 0.00)) {
				$this->is_partial_settle = true;
			}
		}
		// amount check for Sale only
		elseif ($order_total != $dmn_total) {
			$this->sc_transaction_type = 'fraud';

			$this->order->addStatusHistoryComment(
				__('<b>Attention!</b> - There is a problem with the Order. The Order amount is ')
				. $this->order->getOrderCurrencyCode() . ' '
				. $order_total . ', ' . __('but the Paid amount is ')
				. $params['currency'] . ' ' . $dmn_total,
				$this->sc_transaction_type
			);
		}

		$this->orderPayment->setSaleSettleAmount($inv_amount);

		// there are invoices
		if (count($invCollection) > 0) {
			$invoices = [];

			foreach ($this->order->getInvoiceCollection() as $invoice) {
				// Sale
//				if(0 == $dmn_inv_id) {
//					$this->curr_trans_info['invoice_id'][] = $invoice->getId();
//					
//					$invoice
//						->setTransactionId($params['TransactionID'])
//						->setState(Invoice::STATE_PAID)
//						->pay()
//						->save();
//				}
				// Settle
//				else
				if($dmn_inv_id == $invoice->getId()) {
					$this->curr_trans_info['invoice_id'] = $invoice->getId();

					$invoice
						->setTransactionId($params['TransactionID'])
						->setState(Invoice::STATE_PAID)
						->pay()
						->save();
					
					break;
				}
			}
		}
		// there are not invoices, but we can create
		elseif ($this->order->canInvoice()
			&& (
				'sale' == $tr_type_param // Sale flow
				|| ( // APMs flow
					$params["order"] == $params["merchant_unique_id"]
					&& $params["payment_method"] != 'cc_card'
				)
				|| ( // CPanel Settle
					!empty($params["merchant_unique_id"])
					&& $params["merchant_unique_id"] != $params["order"]
				)
			)
		) {
			$this->moduleConfig->createLog('We can create Invoice');

			// create Invoicees and Transactions for non-Magento actions
			// Prepare the invoice
			$invoice = $this->invoiceService->prepareInvoice($this->order);
			$invoice
				->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE)
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
			$this->order->setTotalPaid($inv_amount);
			$this->order->setBaseTotalPaid($inv_amount);

			// for sale ONLY
			if ('sale' == $tr_type_param && $params["payment_method"] == 'cc_card') {
				$invoice->setCanVoidFlag(true);
				$this->order->setCanVoidPayment(true);
				$this->orderPayment->setCanVoid(true);
			}

			$this->curr_trans_info['invoice_id'] = $invoice->getId();

			// Save the invoice
			$this->invoiceRepository->save($invoice);

			$this->orderPayment
				->setIsTransactionPending(0)
				->setIsTransactionClosed(0);

			// set transaction
			$transaction = $this->transObj
				->setPayment($this->orderPayment)
				->setOrder($this->order)
				->setTransactionId($params['TransactionID'])
				->setFailSafe(true)
				->build(Transaction::TYPE_CAPTURE);

			$transaction->save();

			$tr_type	= $this->orderPayment->addTransaction(Transaction::TYPE_CAPTURE);
			$msg        = $this->orderPayment->prependMessage($message);

			$this->orderPayment->addTransactionCommentsToOrder($tr_type, $msg);
		}
		elseif (!$this->order->canInvoice()) {
			$this->moduleConfig->createLog('We can NOT create invoice.');
		}
	}
	
	private function processDeclinedSaleOrSettleDmn()
	{
		$invCollection				= $this->order->getInvoiceCollection();
		$inv_amount					= round(floatval($this->order->getBaseGrandTotal()), 2);
		$dmn_inv_id					= 0;
		
		// there are invoices
		if (count($invCollection) > 0) {
			$this->moduleConfig->createLog(count($invCollection), 'The Invoices count is');

			$invoices = [];

			foreach ($this->order->getInvoiceCollection() as $invoice) {
				// Sale
				if(0 == $dmn_inv_id) {
					$this->curr_trans_info['invoice_id'][] = $invoice->getId();
					
					$invoice
						->setTransactionId($params['TransactionID'])
						->setState(Invoice::STATE_CANCELED)
						->pay()
						->save();
				}
				// Settle
				elseif($dmn_inv_id == $invoice->getId()) {
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
     */
    private function placeOrder($params)
    {
        $this->moduleConfig->createLog(
            [
                'quote' => $params['quote'],
            ],
            'PlaceOrder()'
        );
        
        $result = $this->dataObjectFactory->create();
        
        if (empty($params['quote'])) {
            return $result
                ->setData('error', true)
                ->setData('message', 'Missing Quote parameter.');
        }
        
        try {
            $quote    = $this->quoteFactory->create()->loadByIdWithoutStore((int) $params['quote']);
            $method    = $quote->getPayment()->getMethod();
            
            $this->moduleConfig->createLog(
                [
                    'quote Method' => $method,
                    'quote id' => $quote->getQuoteId(),
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
                    ->setData('message', 'Quote payment method is "'
                        . $method . '"');
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
     * @param array $params
     * @param string $orderIncrementId
     * @return mixed
     */
    private function validateChecksum($params, $orderIncrementId)
    {
        $result = $this->jsonResultFactory->create();
        $result->setHttpResponseCode(200);
        
        if (empty($params["advanceResponseChecksum"]) && empty($params['responsechecksum'])) {
            $msg = 'Required keys advanceResponseChecksum and responsechecksum for checksum calculation are missing.';
            
            $this->moduleConfig->createLog($msg);
            $result->setData($msg);
            
            return $result;
        }
        
        // most of the DMNs with advanceResponseChecksum
        if (!empty($params["advanceResponseChecksum"])) {
            $concat     = $this->moduleConfig->getMerchantSecretKey();
            $params_arr = ['totalAmount', 'currency', 'responseTimeStamp', 'PPP_TransactionID', 'Status', 'productId'];

            foreach ($params_arr as $checksumKey) {
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
                $msg = 'Checksum validation failed for advanceResponseChecksum and Order #' . $orderIncrementId;

                $this->moduleConfig->createLog($msg);

                $result->setData($msg);
                return $result;
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

            $result->setData($msg);
            return $result;
        }
        
        $concat_final = $concat . $this->moduleConfig->getMerchantSecretKey();
        
        $checksum = hash($this->moduleConfig->getHash(), utf8_encode($concat_final));

        if ($params["responsechecksum"] !== $checksum) {
            $msg = 'Checksum validation failed for responsechecksum and Order #' . $orderIncrementId;

            $this->moduleConfig->createLog($concat, $msg);
            $result->setData($msg);
            
            return $result;
        }
        
        return true;
    }
    
    /**
     *
     * @param array $subsc_data - one array to rule them all
     * @param int $plan_id
     * @param int $upo_id
     * @param string $email
     * @param int $order_id - this is increment ID !!!
     * @return type
     */
//    private function createSubscription($plan_id, $upo_id, $email, $order_id) {
//    private function createSubscription($subsc_data) {
    private function createSubscription($subsc_data, $order_id)
    {
        $result = $this->jsonResultFactory->create()->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);
        
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
//                ->setPlanId($plan_id)
//                ->setUpoId($upo_id)
//                ->setUserTokenId($email)
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
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $orderIncrementId, 'eq')->create();

        $tryouts    = 0;
        $max_tries    = 5;
        
        // search only once for Refund/Credit
        if (in_array(strtolower($params['transactionType']), ['refund', 'credit'])) {
            $max_tries = 0;
        }
        
        // do not search more than once for Auth and Sale, if the DMN response time is more than 24 hours before now
        if ($max_tries > 0
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
