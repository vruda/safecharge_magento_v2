<?php

/**
 * Description of View
 *
 * @author SafeCharge
 */

namespace Safecharge\Safecharge\Plugin\Block\Adminhtml\Order\Invoice;

use Safecharge\Safecharge\Model\Payment;

class View extends \Magento\Backend\Block\Widget\Form\Container
{
    private $request;
    private $invoice;
    private $order;
    
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
		\Magento\Sales\Model\Order\Invoice $invoice,
		\Magento\Sales\Model\Order $order
    ) {
        $this->request	= $request;
		$this->invoice	= $invoice;
		$this->order	= $order;
    }
	
	public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\Invoice\View $view)
	{
		try {
			$invoiceDetails = $this->invoice->load($this->request->getParam('invoice_id'));
			$order_incr_id	= $invoiceDetails->getOrder()->getIncrementId();
            $order          = $this->order->loadByIncrementId($order_incr_id);
            $orderPayment	= $order->getPayment();
			$ord_status     = $order->getStatus();
			
			$payment_method	= $orderPayment->getAdditionalInformation(
                Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD
            );
			
			if ($orderPayment->getMethod() === Payment::METHOD_CODE) {
				if (!in_array($payment_method, Payment::PAYMETNS_SUPPORT_REFUND)
					|| in_array($ord_status, [Payment::SC_VOIDED, Payment::SC_PROCESSING])
				) {
					$view->removeButton('credit-memo');
				}
			}
		} catch (Exception $ex) {}
	}
}
