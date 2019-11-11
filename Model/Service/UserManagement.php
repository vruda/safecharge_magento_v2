<?php

namespace Safecharge\Safecharge\Model\Service;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;
use Magento\Framework\Exception\PaymentException;

/**
 * Safecharge Safecharge user management service model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class UserManagement
{
    /**
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * UserManagement constructor.
     *
     * @param RequestFactory $requestFactory
     */
    public function __construct(
        RequestFactory $requestFactory
    ) {
        $this->requestFactory = $requestFactory;
    }

    /**
     * @param int $customerId
     *
     * @return bool|int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getUserId($customerId)
    {
        $userRequest = $this->requestFactory
            ->create(AbstractRequest::GET_USER_DETAILS_METHOD)
            ->setCustomerId($customerId);
        try {
            $userResponse = $userRequest->process();
        } catch (PaymentException $e) {
            return false;
        }

        return $userResponse->getUserId();
    }

    /**
     * @param array $customerData
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createUserId(array $customerData)
    {
        $userRequest = $this->requestFactory
            ->create(AbstractRequest::CREATE_USER_METHOD)
            ->setCustomerData($customerData);

        $userResponse = $userRequest->process();

        return $userResponse->getUserId();
    }
}
