<?php

namespace Nuvei\Payments\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\Config as ModuleConfig;
//use Nuvei\Payments\Model\Logger as NuveiLogger;
//use Nuvei\Payments\Model\Redirect\Url as RedirectUrlBuilder;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;

/**
 * Nuvei Payments paymentApm controller.
 * Combine APMs and UPO APMs payments.
 */
class Apm extends Action
{
    /**
     * @var RedirectUrlBuilder
     */
//    private $redirectUrlBuilder;

    /**
     * @var NuveiLogger
     */
//    private $nuveiLogger;

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
     * @param NuveiLogger   $nuveiLogger
     * @param ModuleConfig       $moduleConfig
     * @param JsonFactory        $jsonResultFactory
     * @param RequestFactory     $requestFactory
     */
    public function __construct(
        Context $context,
//        RedirectUrlBuilder $redirectUrlBuilder,
//        NuveiLogger $nuveiLogger,
        ModuleConfig $moduleConfig,
        JsonFactory $jsonResultFactory,
        RequestFactory $requestFactory
    ) {
        parent::__construct($context);

//        $this->redirectUrlBuilder	= $redirectUrlBuilder;
//        $this->nuveiLogger			= $nuveiLogger;
        $this->moduleConfig         = $moduleConfig;
        $this->jsonResultFactory    = $jsonResultFactory;
        $this->requestFactory       = $requestFactory;
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        $result = $this->jsonResultFactory->create()
            ->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);

        if (!$this->moduleConfig->isActive()) {
            return $result->setData([
				'error_message' => __('Nuvei payments module is not active at the moment!')
			]);
        }

        $params = array_merge(
            $this->getRequest()->getParams(),
            $this->getRequest()->getPostValue()
        );

        $this->moduleConfig->createLog($params, 'Apm Controller incoming params:');

        try {
            $request = $this->requestFactory->create(AbstractRequest::PAYMENT_APM_METHOD);
			
            $response = $request
				->setPaymentMethod($params["chosen_apm_method"] ?: '')
                ->setPaymentMethodFields(empty($params["apm_method_fields"]) ? '' : $params["apm_method_fields"])
                ->setSavePaymentMethod($params["save_payment_method"] ?: 0)
				->process();
            
//            $redirectUrl	= $response->getRedirectUrl();
//            $status         = $response->getResponseStatus();
        }
		catch (PaymentException $e) {
            $this->moduleConfig->createLog(
                [$e->getMessage(), $e->getTraceAsString()],
                'Apm Controller - Exception:'
            );
            
			return $result->setData([
				"error"		=> 1,
				"redirectUrl"	=> null,
				"message"		=> $e->getMessage()
			]);
        }
        
        return $result->setData([
            "error"			=> 0,
            "redirectUrl"	=> $response['redirectUrl'],
//            "redirectUrl"	=> $redirectUrl,
//            "message"		=> $status
            "message"		=> $response['status']
        ]);
    }
}
