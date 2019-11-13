<?php

namespace Safecharge\Safecharge\Model\Response;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge response factory model.
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
        AbstractResponse::TOKEN_HANDLER => \Safecharge\Safecharge\Model\Response\Token::class,
        AbstractResponse::PAYMENT_CC_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\Cc::class,
        AbstractResponse::PAYMENT_SETTLE_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\Settle::class,
        AbstractResponse::PAYMENT_CC_TOKENIZATION_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\CardTokenization::class,
        AbstractResponse::PAYMENT_USER_PAYMENT_OPTION_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\UserPaymentOption::class,
        AbstractResponse::PAYMENT_DYNAMIC_3D_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\Dynamic3D::class,
        AbstractResponse::PAYMENT_PAYMENT_3D_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\Payment3D::class,
        AbstractResponse::CREATE_USER_HANDLER => \Safecharge\Safecharge\Model\Response\CreateUser::class,
        AbstractResponse::GET_USER_DETAILS_HANDLER => \Safecharge\Safecharge\Model\Response\GetUserDetails::class,
        AbstractResponse::PAYMENT_REFUND_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\Refund::class,
        AbstractResponse::PAYMENT_VOID_HANDLER => \Safecharge\Safecharge\Model\Response\Payment\Cancel::class,
        AbstractResponse::OPEN_ORDER_HANDLER => \Safecharge\Safecharge\Model\Response\OpenOrder::class,
        AbstractResponse::PAYMENT_APM_HANDLER => \Safecharge\Safecharge\Model\Response\PaymentApm::class,
        AbstractResponse::GET_MERCHANT_PAYMENT_METHODS_HANDLER => \Safecharge\Safecharge\Model\Response\GetMerchantPaymentMethods::class,
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
     * Create response model.
     *
     * @param string            $type
     * @param int               $requestId
     * @param Curl|null         $curl
     * @param OrderPayment|null $payment
     *
     * @return ResponseInterface
     * @throws LocalizedException
     */
    public function create(
        $type,
        $requestId,
        $curl = null,
        $payment = null
    ) {
        $className = !empty($this->invokableClasses[$type])
            ? $this->invokableClasses[$type]
            : null;

        if ($className === null) {
            throw new LocalizedException(
                __('%1 type is not supported.')
            );
        }

        $model = $this->objectManager->create(
            $className,
            [
                'requestId' => $requestId,
                'curl' => $curl,
                'orderPayment' => $payment,
            ]
        );
        if (!$model instanceof ResponseInterface) {
            throw new LocalizedException(
                __(
                    '%1 doesn\'t implement \Safecharge\Safecharge\Mode\ResponseInterface',
                    $className
                )
            );
        }

        return $model;
    }
}
