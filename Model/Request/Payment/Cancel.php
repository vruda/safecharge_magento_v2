<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction as OrderTransaction;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\AbstractPayment;
use Safecharge\Safecharge\Model\RequestInterface;

/**
 * Safecharge Safecharge void payment request model.
 */
class Cancel extends AbstractPayment implements RequestInterface
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_VOID_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_VOID_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        $orderPayment    = $this->orderPayment;
        $order            = $orderPayment->getOrder();
        $transaction_id    = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_ID);
        
        if (!$transaction_id) {
            throw new PaymentException(
                __('Transaction does not contain Transaction ID code.')
            );
        }

        /** @var OrderTransaction $transaction */
        $transaction        = $orderPayment->getAuthorizationTransaction();
        $transactionDetails    = $transaction->getAdditionalInformation(OrderTransaction::RAW_DETAILS);
        
        $authCode = null;
        if (empty($transactionDetails['authCode'])) {
            $authCode = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
        } else {
            $authCode = $transactionDetails['authCode'];
        }

        if ($authCode === null) {
            throw new PaymentException(
                __('Transaction does not contain authorization code.')
            );
        }
        
        $ref_amount = $orderPayment->getAdditionalInformation(Payment::REFUND_TRANSACTION_AMOUNT);
        
        if ($ref_amount) {
            $amount = $ref_amount;
        } else {
            $amount = (float) $order->getTotalPaid();
        }
        
        $params = [
            'clientUniqueId'        => $order->getIncrementId(),
            'currency'              => $order->getBaseCurrencyCode(),
            'amount'                => $amount,
            'relatedTransactionId'    => $transaction_id,
            'authCode'              => $authCode,
            'comment'               => '',
            'merchant_unique_id'    => $order->getIncrementId(),
            'urlDetails'            => [
                'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
            ],
            'sourceApplication'        => $this->config->getSourceApplication(),
        ];

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
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
            'comment',
            'urlDetails',
            'timeStamp',
        ];
    }
}
