<?php

namespace Safecharge\Safecharge\Model\Request;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\RequestInterface;
use Magento\Framework\Exception\PaymentException;

/**
 * Safecharge Safecharge token request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Token extends AbstractRequest implements RequestInterface
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::GET_SESSION_TOKEN_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::TOKEN_HANDLER;
    }
}
