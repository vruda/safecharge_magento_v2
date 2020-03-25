<?php

/**
 * @author SafeCharge
 */

namespace Safecharge\Safecharge\Plugin\Block\Widget\Button;

use Safecharge\Safecharge\Model\Payment;

class Toolbar
{
    private $config;
    private $orderRepository;
    private $request;
    
    public function __construct(
        \Safecharge\Safecharge\Model\Config $config,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->config            = $config;
        $this->orderRepository    = $orderRepository;
        $this->request            = $request;
    }
    
    /**
     * @param ToolbarContext $toolbar
     * @param AbstractBlock $context
     * @param ButtonList $buttonList
     * @param Config $config
     *
     * @return array
     */
    public function beforePushButtons(
        \Magento\Backend\Block\Widget\Button\Toolbar $toolbar,
        \Magento\Framework\View\Element\AbstractBlock $context,
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    ) {
        if (!$context instanceof \Magento\Sales\Block\Adminhtml\Order\View) {
            return [$context, $buttonList];
        }
        
        try {
            $orderId            = $this->request->getParam('order_id');
            $order                = $this->orderRepository->get($orderId);
            $orderPayment        = $order->getPayment();
            
            if ($orderPayment->getMethod() !== Payment::METHOD_CODE) {
                return [$context, $buttonList];
            }
            
            $ord_status            = $order->getStatus();
            $payment_method        = $orderPayment->getAdditionalInformation(
                Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD
            );
            
            // Examples
    //        $buttonList->update('order_edit', 'class', 'edit');
    //
    //        $buttonList->add('order_review',
    //            [
    //                'label' => __('Review'),
    //                'onclick' => 'setLocation(\'' . $context->getUrl('sales/*/review') . '\')',
    //                'class' => 'review'
    //            ]
    //        );
            
//            $this->config->createLog($buttonList->getItems(), 'buttonList');
            
            if (!in_array($payment_method, Payment::PAYMETNS_SUPPORT_REFUND)
                || in_array($ord_status, [Payment::SC_VOIDED, Payment::SC_PROCESSING])
            ) {
                $buttonList->remove('order_creditmemo');
                $buttonList->remove('credit-memo');
            }
            
            if (Payment::SC_VOIDED == $ord_status) {
                $buttonList->remove('order_invoice');
            }
            
            if ('cc_card' !== $payment_method
                || in_array($ord_status, [Payment::SC_REFUNDED, Payment::SC_PROCESSING, Payment::SC_VOIDED, 'closed'])
            ) {
                $buttonList->remove('void_payment');
            }
        } catch (Exception $e) {
            $this->config->createLog($e->getMessage(), 'Class Toolbar exception:');
            return [$context, $buttonList];
        }

        return [$context, $buttonList];
    }
}
