<?php

namespace Safecharge\Safecharge\Model;

/**
 * Safecharge Safecharge response interface.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
interface ResponseInterface
{
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\PaymentException
     */
    public function process();
}
