<?php

namespace Nuvei\Payments\Controller\Frontend\Order;

use Magento\Sales\Controller\OrderInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;


class SubscriptionsHistory extends \Magento\Framework\App\Action\Action implements OrderInterface, HttpGetActionInterface
{
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        
        parent::__construct($context);
    }
    
    /**
     * Customer order subscriptions history
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('My Nuvei Subscriptions'));

//        $block = $resultPage->getLayout()->getBlock('customer.account.link.back');
//        if ($block) {
//            $block->setRefererUrl($this->_redirect->getRefererUrl());
//        }
        return $resultPage;
    }
}
