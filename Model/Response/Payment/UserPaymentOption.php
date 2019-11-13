<?php

namespace Safecharge\Safecharge\Model\Response\Payment;

use Safecharge\Safecharge\Model\Response\AbstractPayment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge payment user payment option response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class UserPaymentOption extends AbstractPayment implements ResponseInterface
{
    /**
     * @var string
     */
    protected $ccToken;

    /**
     * @return UserPaymentOption
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->ccToken = $body['userPaymentOptionId'];

        return $this;
    }

    /**
     * @return int
     */
    public function getCcToken()
    {
        return $this->ccToken;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return array_merge_recursive(
            parent::getRequiredResponseDataKeys(),
            [
                'userPaymentOptionId',
            ]
        );
    }
}
