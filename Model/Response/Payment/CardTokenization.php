<?php

namespace Safecharge\Safecharge\Model\Response\Payment;

use Safecharge\Safecharge\Model\Response\AbstractPayment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge payment card tokenization response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class CardTokenization extends AbstractPayment implements ResponseInterface
{
    /**
     * @var string
     */
    protected $ccTempToken;

    /**
     * @return CardTokenization
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->ccTempToken = $body['ccTempToken'];

        return $this;
    }

    /**
     * @return int
     */
    public function getCcTempToken()
    {
        return $this->ccTempToken;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return array_merge_recursive(
            parent::getRequiredResponseDataKeys(),
            [
                'isVerified',
                'ccTempToken',
            ]
        );
    }
}
