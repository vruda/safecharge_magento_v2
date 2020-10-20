<?php

namespace Safecharge\Safecharge\Model\Response;

use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge open order response model.
 * 
 * @deprecated since version 2.2.0.x
 */
class OpenOrder extends AbstractResponse implements ResponseInterface
{
    protected $orderId;
    protected $sessionToken;
    protected $amount;

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body               = $this->getBody();
        
        $this->orderId      = $body['orderId'];
        $this->sessionToken = $body['sessionToken'];
        $this->ooAmount        = $body['merchantDetails']['customField1'];

        return $this;
    }

    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }
    
    /**
     * @return string
     */
    public function getSessionToken()
    {
        return $this->sessionToken;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'orderId',
            'sessionToken',
			'merchantDetails',
        ];
    }
}
