<?php

namespace Safecharge\Safecharge\Observer\Sales\Order\Invoice;

use Safecharge\Safecharge\Model\Payment;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * Safecharge Safecharge sales order invoice register observer.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Register implements ObserverInterface
{
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
            return $this;
        }

        /** @var Invoice $invoice */
        $invoice = $observer->getInvoice();

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
