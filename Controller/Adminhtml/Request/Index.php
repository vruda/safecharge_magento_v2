<?php

namespace Nuvei\Payments\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Nuvei Payments admin request index controller.
 */
class Index extends Action
{
    const ADMIN_RESOURCE = 'Nuvei_Payments::sales_nuvei_request';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        $resultPage->setActiveMenu('Nuvei_Payments::sales_nuvei_request');
        $resultPage->getConfig()->getTitle()->prepend(__('Nuvei Api Requests'));

        $resultPage->addBreadcrumb('Nuvei', 'Nuvei');
        $resultPage->addBreadcrumb(__('Api Requests'), __('Api Requests'));

        return $resultPage;
    }
}
