<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\AbstractPayment;
use Safecharge\Safecharge\Model\RequestInterface;

/**
 * Safecharge Safecharge settle payment request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Settle extends AbstractPayment implements RequestInterface
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_SETTLE_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_SETTLE_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $this->orderPayment;

        /** @var Order $order */
        $order = $orderPayment->getOrder();

        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
        $tokenResponse = $tokenRequest->process();

        $this->safechargeLogger->updateRequest(
            $tokenRequest->getRequestId(),
            [
                'parent_request_id' => $orderPayment
                    ->getAdditionalInformation(Payment::TRANSACTION_REQUEST_ID),
            ]
        );

        $authCode = $orderPayment
            ->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
        $relatedTransactionId = $orderPayment
            ->getAdditionalInformation(Payment::TRANSACTION_ID);

        if (!$authCode) {
            throw new PaymentException(__('Authorization code is missing.'));
        }

        $params = [
            'sessionToken' => $tokenResponse->getToken(),
            'clientUniqueId' => $order->getIncrementId(),
            'currency' => $order->getBaseCurrencyCode(),
            'amount' => (float)$this->amount,
            'relatedTransactionId' => $relatedTransactionId,
            'authCode' => $authCode,
            'descriptorMerchantName' => 'Merchant Name',
            'descriptorMerchantPhone' => '12345789',
            'comment' => 'No Comment',
            'merchant_unique_id' => $order->getIncrementId(),
            'urlDetails' => [
                //'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
                'notificationUrl' => '',
            ],
        ];

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
                'parent_request_id' => $tokenRequest->getRequestId(),
                'increment_id' => $order->getIncrementId(),
            ]
        );

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
            'clientRequestId',
            'clientUniqueId',
            'amount',
            'currency',
            'relatedTransactionId',
            'authCode',
            'descriptorMerchantName',
            'descriptorMerchantPhone',
            'comment',
            'urlDetails',
            'timeStamp',
        ];
    }
}
