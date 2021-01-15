<?php

namespace Safecharge\Safecharge\Controller\Adminhtml\System\Config;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;

class GetPlans extends \Magento\Backend\App\Action
{
    protected $jsonResultFactory;
    protected $moduleConfig;
    protected $requestFactory;
    protected $objManager;
    
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Safecharge\Safecharge\Model\Config $moduleConfig,
        RequestFactory $requestFactory
    ) {
        parent::__construct($context);
        
        $this->jsonResultFactory    = $jsonResultFactory;
        $this->moduleConfig            = $moduleConfig;
        $this->requestFactory        = $requestFactory;
    }
    
    public function execute()
    {
        $result = $this->jsonResultFactory->create()->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);

        if (!$this->moduleConfig->isActive()) {
            $this->moduleConfig->createLog('Nuvei payments module is not active at the moment!');
           
			return $result->setData([
				'error_message' => __('Nuvei payments module is not active at the moment!')
			]);
        }
        
        $request = $this->requestFactory->create(AbstractRequest::GET_MERCHANT_PAYMENT_PLANS_METHOD);

        try {
            $plans = $request->process();
        } catch (PaymentException $e) {
            return $result->setData([
             "error"     => 1,
             "message"    => "Error"
            ]);
        }
        
        return $result->setData([
            "success" => 1,
            "message" => "Success"
        ]);
    }
}
