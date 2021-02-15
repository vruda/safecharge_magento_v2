<?php

namespace Nuvei\Payments\Observer\Sales\Order\Invoice;

use Nuvei\Payments\Model\Payment;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * Nuvei Payments sales order invoice after save observer.
 * 
 * We use this observer to get the Invoice ID and pass it into the Settle request.
 */
class AfterSave implements ObserverInterface
{
	protected $objectManager;
	protected $jsonResultFactory;
	
    private $config;
    private $paymentRequestFactory;
    private $requestFactory;
    
    public function __construct(
		\Nuvei\Payments\Model\Config $config,
		\Nuvei\Payments\Model\Request\Payment\Factory $paymentRequestFactory,
		\Nuvei\Payments\Model\Request\Factory $requestFactory,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
	)
    {
        $this->config					= $config;
		$this->paymentRequestFactory	= $paymentRequestFactory;
		$this->requestFactory			= $requestFactory;
		$this->objectManager			= $objectManager;
		$this->jsonResultFactory		= $jsonResultFactory;
    }
    
    /**
     * @param Observer $observer
     *
     * @return Register
     */
    public function execute(Observer $observer)
    {
		try {
			/** @var Invoice $invoice */
			$invoice = $observer->getInvoice();
			
			if(!is_object($invoice)) {
				$this->config->createLog('Invoice AfterSave Observer - $invoice is not an object.');
				return $this;
			}
			
			// if the invoice is Paid, we already made Settle request.
			if($invoice->getState() === Invoice::STATE_PAID) {
				$this->config->createLog($invoice->getId(), 'Invoice AfterSave Observer - the invoice already paid.');
				return $this;
			}

			/** @var Order $order */
			$order = $invoice->getOrder();
			
			if(!is_object($order)) {
				$this->config->createLog('Invoice AfterSave Observer - $order is not an object.');
				return $this;
			}

			/** @var OrderPayment $payment */
			$payment = $order->getPayment();
			
			if(!is_object($payment)) {
				$this->config->createLog('Invoice AfterSave Observer - $payment is not an object.');
				return $this;
			}

			if ($payment->getMethod() !== Payment::METHOD_CODE) {
				$this->config->createLog($payment->getMethod(), 'Invoice AfterSave Observer Error - payment method is');

				return $this;
			}

			// Settle request
			$authCode                = '';
	        $ord_trans_addit_info    = $payment->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
	        
	        if (is_array($ord_trans_addit_info) && !empty($ord_trans_addit_info)) {
	            foreach ($ord_trans_addit_info as $trans) {
	                if (strtolower($trans[Payment::TRANSACTION_TYPE]) == 'auth'
	                    && strtolower($trans[Payment::TRANSACTION_STATUS]) == 'approved'
	                ) {
	                    $authCode = $trans[Payment::TRANSACTION_AUTH_CODE];
	                    break;
	                }
	            }
	        }
			
			if (empty($authCode)) {
				$this->config->createLog('Invoice AfterSave Observer - $authCode is empty.');
				
	            $payment->setIsTransactionPending(true); // TODO do we need this
	            return $this;
	        }
			
//				$request = $this->requestFactory->create(\Nuvei\Payments\Model\AbstractRequest::SETTLE_METHOD);
//				$request = $this->objectManager->create(\Nuvei\Payments\Model\AbstractRequest::SETTLE_METHOD);
			$request = $this->objectManager->create(\Nuvei\Payments\Model\Request\SettleTransaction::class);

			$resp = $request
				->setPayment($payment)
				->setInvoiceId($invoice->getId())
				->setInvoiceAmount($invoice->getGrandTotal())
				->process();

			/** @var RequestInterface $request */
//	        $request = $this->paymentRequestFactory->create(
//	            \Nuvei\Payments\Model\AbstractRequest::PAYMENT_SETTLE_METHOD
//	            ,$payment
////	            ,$invoice->getGrandTotal()
////				,$invoice->getId()
//	        );
//	        
//	        $response = $request
//				->setInvoiceId($invoice->getId())
//				->setInvoiceAmount($invoice->getGrandTotal())
//				->process();
	        
//	        if ($authCode === null) {
//	            $this->checkoutSession->setRedirectUrl($response->getRedirectUrl());
//	        }
			// Settle request END
		}
		catch(Exception $e) {
			$this->config->createLog($e->getMessage(), 'Invoice AfterSave Exception');
		}
		
        return $this;
    }
}
