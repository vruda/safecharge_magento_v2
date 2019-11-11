<?php

namespace Safecharge\Safecharge\Model\Service;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;

/**
 * Safecharge Safecharge order management service model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class OrderManagement
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
     * @param array $orderData
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function openOrder(array $orderData)
    {
        $userRequest = $this->requestFactory
            ->create(AbstractRequest::OPEN_ORDER_METHOD)
            ->setOrderData($orderData);

        $orderResponse = $userRequest->process();

        return $orderResponse->getOrderId();
    }
}
