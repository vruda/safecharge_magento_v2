<?php

namespace Safecharge\Safecharge\Model\Response;

use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge token response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Token extends AbstractResponse implements ResponseInterface
{
    /**
     * @var string
     */
    protected $token;

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        $this->token = $body['sessionToken'];

        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'sessionToken',
        ];
    }
}
