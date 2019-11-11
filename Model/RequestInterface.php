<?php

namespace Safecharge\Safecharge\Model;

/**
 * Safecharge Safecharge request interface.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
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
