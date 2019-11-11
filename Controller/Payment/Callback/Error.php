<?php

namespace Safecharge\Safecharge\Controller\Payment\Callback;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Safecharge Safecharge payment place controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Error extends Action implements CsrfAwareActionInterface
{
    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * Error constructor.
     *
     * @param Context          $context
     * @param SafechargeLogger $safechargeLogger
     * @param ModuleConfig     $moduleConfig
     */
    public function __construct(
        Context $context,
        SafechargeLogger $safechargeLogger,
        ModuleConfig $moduleConfig
    ) {
        parent::__construct($context);

        $this->safechargeLogger = $safechargeLogger;
        $this->moduleConfig = $moduleConfig;
    }
	
	/** 
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request 
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @return ResultInterface
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();

        if ($this->moduleConfig->isDebugEnabled() === true) {
            $this->safechargeLogger->debug(
                'Error Callback Response: '
                . var_export($params, true)
            );
        }

        $this->messageManager->addErrorMessage(
            __('Your payment failed.')
        );

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'));

        return $resultRedirect;
    }
}
