<?php

namespace Safecharge\Safecharge\Model\Request;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;

/**
 * Safecharge Safecharge create user request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class CreateUser extends AbstractRequest implements RequestInterface
{
    /**
     * @var array
     */
    protected $customerData;

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::CREATE_USER_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::CREATE_USER_HANDLER;
    }

    /**
     * @param array $customerData
     *
     * @return CreateUser
     */
    public function setCustomerData(array $customerData)
    {
        $this->customerData = $customerData;

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
        if ($this->customerData === null) {
            throw new PaymentException(__('Customer data has been not set.'));
        }

        $params = array_merge_recursive($this->customerData, parent::getParams());

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
            'firstName',
            'lastName',
            'address',
            'state',
            'city',
            'zip',
            'countryCode',
            'phone',
            'locale',
            'email',
            'timeStamp',
        ];
    }
}
