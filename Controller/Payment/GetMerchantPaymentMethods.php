<?php

namespace Nuvei\Payments\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\Config as ModuleConfig;
use Nuvei\Payments\Model\Logger as Logger;
use Nuvei\Payments\Model\Redirect\Url as RedirectUrlBuilder;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;

/**
 * Nuvei Payments GetMerchantPaymentMethods controller.
 */
class GetMerchantPaymentMethods extends Action
{
    /**
     * @var RedirectUrlBuilder
     */
    private $redirectUrlBuilder;

    /**
     * @var Logger
     */
    private $logger;

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
     * @param Logger   $logger
     * @param ModuleConfig       $moduleConfig
     * @param JsonFactory        $jsonResultFactory
     * @param RequestFactory     $requestFactory
     */
    public function __construct(
        Context $context,
        RedirectUrlBuilder $redirectUrlBuilder,
        Logger $logger,
        ModuleConfig $moduleConfig,
        JsonFactory $jsonResultFactory,
        RequestFactory $requestFactory
    ) {
        parent::__construct($context);

        $this->redirectUrlBuilder    = $redirectUrlBuilder;
        $this->logger        = $logger;
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

		$apmMethodsData = $this->getApmMethods();

        return $result->setData([
            "error"            => 0,
            "apmMethods"    => $apmMethodsData['apmMethods'],
            "sessionToken"    => $apmMethodsData['sessionToken'],
            "message"        => "Success"
        ]);
    }

    /**
     * Return AMP Methods.
     * We pass both parameters from JS via Ajax request
     *
     * @return array
     */
    private function getApmMethods()
    {
        $request = $this->requestFactory->create(AbstractRequest::GET_MERCHANT_PAYMENT_METHODS_METHOD);

		$apmMethods = $request
			->setBillingAddress($this->getRequest()->getParam('billingAddress'))
			->process();
        
        return [
            'apmMethods'	=> $apmMethods->getScPaymentMethods(),
            'sessionToken'  => $apmMethods->getSessionToken(),
        ];
    }
}
