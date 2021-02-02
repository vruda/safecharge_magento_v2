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
 * Nuvei Payments GetUpos controller.
 */
class GetUpos extends Action
{
    /**
     * @var RedirectUrlBuilder
     */
    private $redirectUrlBuilder;

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
     * @param ModuleConfig       $moduleConfig
     * @param JsonFactory        $jsonResultFactory
     * @param RequestFactory     $requestFactory
     */
    public function __construct(
        Context $context,
        RedirectUrlBuilder $redirectUrlBuilder,
        ModuleConfig $moduleConfig,
        JsonFactory $jsonResultFactory,
        RequestFactory $requestFactory
    ) {
        parent::__construct($context);

        $this->redirectUrlBuilder	= $redirectUrlBuilder;
        $this->moduleConfig         = $moduleConfig;
        $this->jsonResultFactory    = $jsonResultFactory;
        $this->requestFactory       = $requestFactory;
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
            $UPOs_data = $this->getUPOs();
        } catch (PaymentException $e) {
            $this->moduleConfig->createLog('GetUpos Controller - Exception: ' . $e->getMessage());
            
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

    /**
     * Return AMP Methods.
     * We pass both parameters from JS via Ajax request
     *
     * @return array
     */
    private function getUPOs()
    {
        $request = $this->requestFactory->create(AbstractRequest::GET_UPOS_METHOD);

        try {
            $UPOs = $request
                ->process();
        } catch (PaymentException $e) {
            return [];
        }
        
        return [
            'UPOs' => $UPOs->getScPaymentMethods(),
        ];
    }
}
