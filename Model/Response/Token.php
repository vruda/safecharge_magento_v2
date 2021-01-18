<?php

namespace Nuvei\Payments\Model\Response;

use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\ResponseInterface;

/**
 * Nuvei Payments token response model.
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
