<?php

namespace Safecharge\Safecharge\Model\Response;

use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge get user details response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
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
