<?php

namespace Safecharge\Safecharge\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Redirect\Url as RedirectUrlBuilder;

/**
 * Safecharge Safecharge payment redirect controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Redirect extends Action
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
     * Redirect constructor.
     *
     * @param Context            $context
     * @param RedirectUrlBuilder $redirectUrlBuilder
     * @param SafechargeLogger   $safechargeLogger
     * @param ModuleConfig       $moduleConfig
     * @param JsonFactory        $jsonResultFactory
     */
    public function __construct(
        Context $context,
        RedirectUrlBuilder $redirectUrlBuilder,
        SafechargeLogger $safechargeLogger,
        ModuleConfig $moduleConfig,
        JsonFactory $jsonResultFactory
    ) {
        parent::__construct($context);

        $this->redirectUrlBuilder = $redirectUrlBuilder;
        $this->safechargeLogger = $safechargeLogger;
        $this->moduleConfig = $moduleConfig;
        $this->jsonResultFactory = $jsonResultFactory;
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        $result = $this->jsonResultFactory->create()
            ->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK);

        if (!$this->moduleConfig->isActive()) {
            if ($this->moduleConfig->isDebugEnabled()) {
                $this->safechargeLogger->debug('Redirect Controller: Safecharge payments module is not active at the moment!');
            }
            return $result->setData(['error_message' => __('Safecharge payments module is not active at the moment!')]);
        }

        $postData = $this->redirectUrlBuilder->getPostData();

        if ($this->moduleConfig->isDebugEnabled()) {
            $this->safechargeLogger->debug('PostData: ' . print_r($postData, 1));
        }

        return $result->setData($postData);
    }
}
