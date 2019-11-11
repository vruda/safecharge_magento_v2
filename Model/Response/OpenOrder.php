<?php

namespace Safecharge\Safecharge\Model\Response;

use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge open order response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class OpenOrder extends AbstractResponse implements ResponseInterface
{
    /**
     * @var string
     */
    protected $orderId;
	
	/**
     * @var string
     */
    protected $sessionToken;

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        $this->orderId		= $body['orderId'];
        $this->sessionToken = $body['sessionToken'];

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
			'sessionToken'
        ];
    }
}
