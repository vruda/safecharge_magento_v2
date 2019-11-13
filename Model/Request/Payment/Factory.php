<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\RequestInterface;

/**
 * Safecharge Safecharge payment request factory model.
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
        AbstractRequest::PAYMENT_CC_METHOD => \Safecharge\Safecharge\Model\Request\Payment\Cc::class,
        AbstractRequest::PAYMENT_SETTLE_METHOD => \Safecharge\Safecharge\Model\Request\Payment\Settle::class,
        AbstractRequest::PAYMENT_CARD_TOKENIZATION_METHOD => \Safecharge\Safecharge\Model\Request\Payment\CardTokenization::class,
        AbstractRequest::PAYMENT_USER_PAYMENT_OPTION_METHOD => \Safecharge\Safecharge\Model\Request\Payment\UserPaymentOption::class,
        AbstractRequest::PAYMENT_DYNAMIC_3D_METHOD => \Safecharge\Safecharge\Model\Request\Payment\Dynamic3D::class,
        AbstractRequest::PAYMENT_PAYMENT_3D_METHOD => \Safecharge\Safecharge\Model\Request\Payment\Payment3D::class,
        AbstractRequest::PAYMENT_REFUND_METHOD => \Safecharge\Safecharge\Model\Request\Payment\Refund::class,
        AbstractRequest::PAYMENT_VOID_METHOD => \Safecharge\Safecharge\Model\Request\Payment\Cancel::class,
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
     * @param string       $method
     * @param OrderPayment $orderPayment
     * @param float        $amount
     *
     * @return RequestInterface
     * @throws LocalizedException
     */
    public function create($method, $orderPayment, $amount = 0.0)
    {
        $className = !empty($this->invokableClasses[$method])
            ? $this->invokableClasses[$method]
            : null;

        if ($className === null) {
            throw new LocalizedException(
                __('%1 method is not supported.')
            );
        }

        $model = $this->objectManager->create(
            $className,
            [
                'orderPayment' => $orderPayment,
                'amount' => $amount,
            ]
        );
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
