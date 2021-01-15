<?php

namespace Safecharge\Safecharge\Observer\Sales\Order\Invoice;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Safecharge\Safecharge\Model\Payment;

/**
 * Safecharge Safecharge sales order invoice pay observer.
 */
class Pay implements ObserverInterface
{
	private $config;
	
	public function __construct(\Safecharge\Safecharge\Model\Config $config) {
		$this->config = $config;
	}
	
    /**
     * @param Observer $observer
     *
     * @return Pay
     */
    public function execute(Observer $observer)
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getInvoice();

        /** @var Order $order */
        $order = $invoice->getOrder();

        /** @var OrderPayment $payment */
        $payment = $order->getPayment();

        if ($payment->getMethod() !== Payment::METHOD_CODE) {
			$this->config->createLog($payment->getMethod(), 'Invoice Pay Observer Error - payment method is');
			
            return $this;
        }

        if ($invoice->getState() !== Invoice::STATE_PAID) {
			$this->config->createLog($invoice->getState(), 'Invoice Pay Observer Error - $invoice state is');
			
            return $this;
        }

        return $this;
    }
}
