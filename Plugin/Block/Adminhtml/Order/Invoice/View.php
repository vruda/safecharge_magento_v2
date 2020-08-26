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
    private $config;
    private $orderRepo;
    private $searchCriteriaBuilder;
    
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Sales\Model\Order\Invoice $invoice,
		\Safecharge\Safecharge\Model\Config $config,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepo,
		\Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->request    = $request;
        $this->invoice    = $invoice;
        $this->config    = $config;
        $this->orderRepo    = $orderRepo;
        $this->searchCriteriaBuilder    = $searchCriteriaBuilder;
    }
    
    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\Invoice\View $view)
    {
        try {
            $invoiceDetails = $this->invoice->load($this->request->getParam('invoice_id'));
            $order_incr_id    = $invoiceDetails->getOrder()->getIncrementId();
			
			$searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $order_incr_id, 'eq')->create();
			$orderList = $this->orderRepo->getList($searchCriteria)->getItems();

			if (!$orderList || empty($orderList)) {
				$this->config->createLog('Modify Order Invoice buttons error - there is no $orderList');
				return;
			}
	
			$order			= current($orderList);
            $orderPayment    = $order->getPayment();
            $ord_status     = $order->getStatus();
            
            $payment_method    = $orderPayment->getAdditionalInformation(
                Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD
            );
            
            if ($orderPayment->getMethod() === Payment::METHOD_CODE) {
                if (!in_array($payment_method, Payment::PAYMETNS_SUPPORT_REFUND)
                    || in_array($ord_status, [Payment::SC_VOIDED, Payment::SC_PROCESSING])
                ) {
                    $view->removeButton('credit-memo');
                }
                
                if ('cc_card' !== $payment_method
                    || in_array($ord_status, [Payment::SC_REFUNDED, Payment::SC_PROCESSING, Payment::SC_VOIDED, 'closed'])
                ) {
                    $view->removeButton('void');
                }
            }
        } catch (Exception $ex) {
			$this->config->createLog($ex->getMessage(), 'admin beforeSetLayout');
        }
    }
}
