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
 * Safecharge Safecharge GetMerchantPaymentMethods controller.
 */
class GetMerchantPaymentMethods extends Action
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

        try {
//            $countryCode    = $this->getRequest()->getParam('countryCode');
//            $grandTotal     = $this->getRequest()->getParam('grandTotal');
//            $billingAddress	= $this->getRequest()->getParam('billingAddress');
//            
//            $apmMethodsData = $this->getApmMethods($countryCode, $grandTotal, $billingAddress);
            $apmMethodsData = $this->getApmMethods();
        } catch (PaymentException $e) {
            $this->moduleConfig->createLog('GetMerchantPaymentMethods Controller - Error: ' . $e->getMessage());
            
            return $result->setData([
                "error"            => 1,
                "apmMethods"    => [],
                "message"        => $e->getMessage()
            ]);
        }

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
     * @param string $countryCode
     * @param string $grandTotal
     * @param array $billingAddress parameters
     *
     * @return array
     */
//    private function getApmMethods($countryCode = null, $grandTotal = null, $billingAddress = [])
    private function getApmMethods()
    {
        $request = $this->requestFactory->create(AbstractRequest::GET_MERCHANT_PAYMENT_METHODS_METHOD);

//		$this->moduleConfig->createLog(
//			[
//				'$countryCode' => $countryCode,
//				'$grandTotal' => $grandTotal,
//				'$billingAddress' => $billingAddress,
//			],
//			'GetMerchantPaymentMethods->getApmMethods() controller'
//		);
		
		$apmMethods = $request
//                ->setCountryCode($countryCode)
//                ->setBillingAddress($billingAddress)
			->process();
        
        return [
            'apmMethods'	=> $apmMethods->getScPaymentMethods(),
            'sessionToken'  => $apmMethods->getSessionToken(),
        ];
    }
}
