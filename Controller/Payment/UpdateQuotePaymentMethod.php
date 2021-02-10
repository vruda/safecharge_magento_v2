<?php

namespace Nuvei\Payments\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Nuvei\Payments\Model\Config as ModuleConfig;
use Nuvei\Payments\Model\Logger as Logger;
use Nuvei\Payments\Model\Redirect\Url as RedirectUrlBuilder;
use Nuvei\Payments\Model\Request\Factory as RequestFactory;

/**
 * Nuvei Payments UpdateQuotePaymentMethod controller.
 */
class UpdateQuotePaymentMethod extends Action
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

        $this->moduleConfig            = $moduleConfig;
        $this->jsonResultFactory    = $jsonResultFactory;
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        $result = $this
            ->jsonResultFactory
            ->create()
            ->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);
        
        $this->moduleConfig->createLog(
            $this->getRequest()->getParam('paymentMethod'),
            'Class UpdateQuotePaymentMethod'
        );
        
        $this->moduleConfig->setQuotePaymentMethod($this->getRequest()->getParam('paymentMethod'));
        
        return $result->setData([
            "error"            => 0,
            "message"        => "Success"
        ]);
    }
}
