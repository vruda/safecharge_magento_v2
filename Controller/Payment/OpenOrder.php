<?php

namespace Nuvei\Payments\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\Config as ModuleConfig;
use Nuvei\Payments\Model\Logger as SafechargeLogger;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;

/**
 * Nuvei Payments OpenOrder controller.
 */
class OpenOrder extends Action
{
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
//        RedirectUrlBuilder $redirectUrlBuilder,
        SafechargeLogger $safechargeLogger,
        ModuleConfig $moduleConfig,
        JsonFactory $jsonResultFactory,
        RequestFactory $requestFactory
    ) {
        parent::__construct($context);

//        $this->redirectUrlBuilder    = $redirectUrlBuilder;
//        $this->safechargeLogger        = $safechargeLogger;
        $this->moduleConfig            = $moduleConfig;
        $this->jsonResultFactory    = $jsonResultFactory;
        $this->requestFactory        = $requestFactory;
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        $result = $this->jsonResultFactory->create()->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);

        if (!$this->moduleConfig->isActive()) {
            $this->moduleConfig->createLog('Nuvei payments module is not active at the moment!');
            
			return $result->setData([
				'error_message' => __('Nuvei payments module is not active at the moment!')
			]);
        }
		
		$resp = $this->openOrder();

        return $result->setData([
            "error"         => 0,
            "sessionToken"	=> $resp['sessionToken'],
            "message"       => "Success"
        ]);
    }

    /**
     * @return array
     */
    private function openOrder()
    {
        $request	= $this->requestFactory->create(AbstractRequest::OPEN_ORDER_METHOD);
		$resp		= $request->process();
        
        return [
			'sessionToken' => $resp->sessionToken
		];
    }
}
