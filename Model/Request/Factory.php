<?php

namespace Safecharge\Safecharge\Model\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\RequestInterface;

/**
 * Safecharge Safecharge request factory model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Factory
{
    /**
     * Set of requests.
     *
     * @var array
     */
    private $invokableClasses = [
        AbstractRequest::GET_SESSION_TOKEN_METHOD => \Safecharge\Safecharge\Model\Request\Token::class,
        AbstractRequest::CREATE_USER_METHOD => \Safecharge\Safecharge\Model\Request\CreateUser::class,
        AbstractRequest::GET_USER_DETAILS_METHOD => \Safecharge\Safecharge\Model\Request\GetUserDetails::class,
        AbstractRequest::OPEN_ORDER_METHOD => \Safecharge\Safecharge\Model\Request\OpenOrder::class,
        AbstractRequest::PAYMENT_APM_METHOD => \Safecharge\Safecharge\Model\Request\PaymentApm::class,
        AbstractRequest::GET_MERCHANT_PAYMENT_METHODS_METHOD => \Safecharge\Safecharge\Model\Request\GetMerchantPaymentMethods::class
    ];

    /**
     * Object manager object.
     *
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Construct
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create request model.
     *
     * @param string $method
     *
     * @return RequestInterface
     * @throws LocalizedException
     */
    public function create($method)
    {
        $className = !empty($this->invokableClasses[$method])
            ? $this->invokableClasses[$method]
            : null;

        if ($className === null) {
            throw new LocalizedException(
                __('%1 method is not supported.')
            );
        }

        $model = $this->objectManager->create($className);
        if (!$model instanceof RequestInterface) {
            throw new LocalizedException(
                __(
                    '%1 doesn\'t implement \Safecharge\Safecharge\Mode\RequestInterface',
                    $className
                )
            );
        }

        return $model;
    }
}
