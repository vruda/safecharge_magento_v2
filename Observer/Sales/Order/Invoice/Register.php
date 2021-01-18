<?php

namespace Nuvei\Payments\Observer\Sales\Order\Invoice;

use Nuvei\Payments\Model\Payment;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * Nuvei Payments sales order invoice register observer.
 */
class Register implements ObserverInterface
{
	private $config;
	
	public function __construct(\Nuvei\Payments\Model\Config $config) {
		$this->config = $config;
	}
	
    /**
     * @param Observer $observer
     *
     * @return Register
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();

        /** @var OrderPayment $payment */
        $payment = $order->getPayment();

        if ($payment->getMethod() !== Payment::METHOD_CODE) {
			$this->config->createLog($payment->getMethod(), 'Invoice Register Observer Error - payment method is');
			
            return $this;
        }

        /** @var Invoice $invoice */
        $invoice = $observer->getInvoice();

        if ($invoice->getState() !== Invoice::STATE_PAID) {
			$this->config->createLog($invoice->getState(), 'Invoice Register Observer Error - $invoice state is');
			
            return $this;
        }

//        $status = Payment::SC_SETTLED;
//
//        $totalDue = $order->getBaseTotalDue();
//        if ((float)$totalDue > 0.0) {
//            $status = Payment::SC_PARTIALLY_SETTLED;
//        }

//        $order->setStatus($status);

        return $this;
    }
}
