<?php

namespace Safecharge\Safecharge\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Safecharge Safecharge admin request index controller.
 */
class Index extends Action
{
    const ADMIN_RESOURCE = 'Safecharge_Safecharge::sales_safecharge_request';

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

        $resultPage->setActiveMenu('Safecharge_Safecharge::sales_safecharge_request');
        $resultPage->getConfig()->getTitle()->prepend(__('Nuvei Api Requests'));

        $resultPage->addBreadcrumb('Nuvei', 'Nuvei');
        $resultPage->addBreadcrumb(__('Api Requests'), __('Api Requests'));

        return $resultPage;
    }
}
