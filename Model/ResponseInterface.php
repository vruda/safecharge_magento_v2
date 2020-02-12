<?php

namespace Safecharge\Safecharge\Model;

/**
 * Safecharge Safecharge response interface.
 */
interface ResponseInterface
{
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\PaymentException
     */
    public function process();
}
