<?php

namespace Safecharge\Safecharge\Model;

/**
 * Safecharge Safecharge request interface.
 */
interface RequestInterface
{
    /**
     * Process current request type.
     *
     * @return RequestInterface
     * @throws \Magento\Framework\Exception\PaymentException
     */
    public function process();
}
