<?php

namespace Safecharge\Safecharge\Model\Request;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\RequestInterface;
use Magento\Framework\Exception\PaymentException;

/**
 * Safecharge Safecharge get user details request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class GetUserDetails extends AbstractRequest implements RequestInterface
{
    /**
     * @var int|null
     */
    protected $customerId;

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::GET_USER_DETAILS_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::GET_USER_DETAILS_HANDLER;
    }

    /**
     * @param int $customerId
     *
     * @return GetUserDetails
     */
    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     */
    protected function getParams()
    {
        if ($this->customerId === null) {
            throw new PaymentException(__('Customer id has been not set.'));
        }

        $params = ['userTokenId' => $this->customerId];
        $params = array_merge_recursive($params, parent::getParams());

        return $params;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'userTokenId',
            'clientRequestId',
            'timeStamp',
        ];
    }
}
