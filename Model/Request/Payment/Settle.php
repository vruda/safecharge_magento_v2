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

        $authCode = $orderPayment
            ->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
        $relatedTransactionId = $orderPayment
            ->getAdditionalInformation(Payment::TRANSACTION_ID);

        if (!$authCode) {
            throw new PaymentException(__('Authorization code is missing.'));
        }
		
		$getIncrementId = $order->getIncrementId();

        $params = [
            'clientUniqueId'			=> $getIncrementId,
			'amount'					=> (float)$this->amount,
            'currency'					=> $order->getBaseCurrencyCode(),
            'relatedTransactionId'		=> $relatedTransactionId,
			'authCode'					=> $authCode,
			'urlDetails'				=> [
                'notificationUrl' => $this->config->getCallbackDmnUrl($getIncrementId),
            ],
        ];

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
            'clientRequestId',
            'clientUniqueId',
            'amount',
            'currency',
            'relatedTransactionId',
            'authCode',
            'urlDetails',
            'timeStamp',
        ];
    }
}
