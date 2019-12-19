<?php

namespace Safecharge\Safecharge\Observer\Sales\Order\Payment;

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
class Refund implements ObserverInterface
{
    /**
     * @param Observer $observer
     *
     * @return Pay
     */
    public function execute(Observer $observer)
    {
        /** @var OrderPayment $payment */
        $payment = $order->getPayment();

        if ($payment->getMethod() !== Payment::METHOD_CODE) {
            return $this;
        }
		
		$order = $payment->getOrder();
        $order->setStatus(Payment::SC_REFUNDED);

        return $this;
    }
}
