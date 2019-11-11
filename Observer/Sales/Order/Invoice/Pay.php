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
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Pay implements ObserverInterface
{
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
            return $this;
        }

        if ($invoice->getState() !== Invoice::STATE_PAID) {
            return $this;
        }

        $status = Payment::SC_SETTLED;

        $totalDue = $order->getBaseTotalDue();
        if ((float)$totalDue > 0.0) {
            $status = Payment::SC_PARTIALLY_SETTLED;
        }

        $order->setStatus($status);

        return $this;
    }
}
