<?php

namespace Safecharge\Safecharge\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Redirect\Url as RedirectUrlBuilder;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;

/**
 * Safecharge Safecharge GetUpos controller.
 */
class CreateSubscription extends Action
{
    /**
     * @var RedirectUrlBuilder
     */
    private $redirectUrlBuilder;

    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * Redirect constructor.
     *
     * @param Context            $context
     * @param RedirectUrlBuilder $redirectUrlBuilder
     * @param SafechargeLogger   $safechargeLogger
     * @param ModuleConfig       $moduleConfig
     * @param JsonFactory        $jsonResultFactory
     * @param RequestFactory     $requestFactory
     */
    public function __construct(
        Context $context,
        RedirectUrlBuilder $redirectUrlBuilder,
        SafechargeLogger $safechargeLogger,
        ModuleConfig $moduleConfig,
        JsonFactory $jsonResultFactory,
        RequestFactory $requestFactory
    ) {
        parent::__construct($context);

        $this->redirectUrlBuilder    = $redirectUrlBuilder;
        $this->safechargeLogger        = $safechargeLogger;
        $this->moduleConfig            = $moduleConfig;
        $this->jsonResultFactory    = $jsonResultFactory;
        $this->requestFactory        = $requestFactory;
    }

    /**
	 * @param int $plan_id
	 * @param int $upo_id
	 * 
     * @return ResponseInterface
     */
    public function execute()
    {
		return 'test';
		
		
        $result = $this->jsonResultFactory->create()->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);

        if (!$this->moduleConfig->isActive()) {
            $this->moduleConfig->createLog('Safecharge payments module is not active at the moment!');
            return $result->setData(['error_message' => __('Safecharge payments module is not active at the moment!')]);
        }

        try {
            $request = $this->requestFactory->create(AbstractRequest::GET_UPOS_METHOD);
			$result = $request
				->setPlanId($plan_id)
				->setUpoId($upo_id)
				->process();

			return [
				'UPOs' => $UPOs->getScPaymentMethods(),
			];
        }
		catch (PaymentException $e) {
            $this->moduleConfig->createLog('GetUpos Controller - Error: ' . $e->getMessage());
            
            return $result->setData([
                "error"     => 1,
                "UPOs"		=> [],
                "message"	=> $e->getMessage()
            ]);
        }

        return $result->setData([
            "error"     => 0,
            "UPOs"		=> $UPOs_data['paymentMethods'],
            "message"	=> "Success"
        ]);
    }
}
