<?php

namespace Safecharge\Safecharge\Model\Request;

use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge abstract payment request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
abstract class AbstractPayment extends AbstractRequest
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var PaymentRequestFactory
     */
    protected $paymentRequestFactory;

    /**
     * @var OrderPayment
     */
    protected $orderPayment;

    /**
     * @var float
     */
    protected $amount;

    /**
     * AbstractPayment constructor.
     *
     * @param SafechargeLogger      $safechargeLogger
     * @param Config                $config
     * @param Curl                  $curl
     * @param RequestFactory        $requestFactory
     * @param PaymentRequestFactory $paymentRequestFactory
     * @param ResponseFactory       $responseFactory
     * @param OrderPayment|null     $orderPayment
     * @param float|null            $amount
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        RequestFactory $requestFactory,
        PaymentRequestFactory $paymentRequestFactory,
        ResponseFactory $responseFactory,
        OrderPayment $orderPayment,
        $amount = 0.0
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory = $requestFactory;
        $this->paymentRequestFactory = $paymentRequestFactory;
        $this->orderPayment = $orderPayment;
        $this->amount = $amount;
    }

    /**
     * {@inheritdoc}
     *
     * @return ResponseInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getResponseHandler()
    {
        $responseHandler = $this->responseFactory->create(
            $this->getResponseHandlerType(),
            $this->getRequestId(),
            $this->curl,
            $this->orderPayment
        );

        return $responseHandler;
    }

    /**
     * Return action type.
     *
     * @return string
     * @throws PaymentException
     */
    protected function getActionType()
    {
        $paymentAction = $this->config->getPaymentAction();
        if ($paymentAction === Payment::ACTION_AUTHORIZE) {
            return 'Auth';
        }
        if ($paymentAction === Payment::ACTION_AUTHORIZE_CAPTURE) {
            return 'Sale';
        }

        throw new PaymentException(__('Unsupported payment action type.'));
    }
}
