<?php

namespace Nuvei\Payments\Model\Response;

use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\ResponseInterface;

/**
 * Nuvei Payments get user details response model.
 */
class GetUserDetails extends AbstractResponse implements ResponseInterface
{
    /**
     * @var string
     */
    protected $userId;

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        $this->userId = $body['userDetails']['userId'];

        return $this;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'userDetails',
        ];
    }
}
