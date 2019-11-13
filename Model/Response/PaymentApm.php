<?php

namespace Safecharge\Safecharge\Model\Response;

use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge paymentAPM response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class PaymentApm extends AbstractResponse implements ResponseInterface
{

    /**
     * @var string
     */
    protected $redirectUrl = "";

    /**
     * @var string
     */
    protected $responseStatus = "";

    /**
     * @return PaymentApm
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        $this->redirectUrl = (string) $body['redirectURL'];
        $this->responseStatus = (string) $body['status'];

        return $this;
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @return string
     */
    public function getResponseStatus()
    {
        return $this->responseStatus;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'redirectURL',
            'status',
        ];
    }
}
