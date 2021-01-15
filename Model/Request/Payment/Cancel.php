<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
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
        // we can create Void for Settle and Auth only!!!
        $orderPayment        = $this->orderPayment;
        $order                = $orderPayment->getOrder();
        $order_auth_data    = $orderPayment->getAdditionalInformation(Payment::AUTH_PARAMS);
        $transaction_id    = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_ID);
        
        $this->config->createLog($order_auth_data, '$order_auth_data');
        $this->config->createLog($transaction_id, '$transaction_id');
        
        if (!$transaction_id) {
            $msg = 'Transaction does not contain Transaction ID code.';
            $this->config->createLog('Void error: ' . $msg);
            
            throw new PaymentException(
                __($msg)
            );
        }
        
        // Settle
        if ($transaction_id !== $order_auth_data['TransactionID']) {
            $transaction    = $orderPayment->getAuthorizationTransaction();
            $authCode        = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_ID);
            $authCode        = null;

            if (empty($transactionDetails['authCode'])) {
                $authCode = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
            } else {
                $authCode = $transactionDetails['authCode'];
            }
            
//            $ref_amount = $orderPayment->getAdditionalInformation(Payment::REFUND_TRANSACTION_AMOUNT);
//
//            if ($ref_amount) {
//                $amount = $ref_amount;
//            } else {
                $amount = (float) $order->getTotalPaid();
//            }
            
        } else { // Auth
            $authCode    = $order_auth_data['AuthCode'];
            $amount        = $order_auth_data['totalAmount'];
        }

        if (empty($authCode)) {
            $this->config->createLog('Void error: Transaction does not contain authorization code.');
            
            throw new PaymentException(
                __('Transaction does not contain authorization code.')
            );
        }
        
        if (empty($amount)) {
            $this->config->createLog('Void error: totalAmount is empty.');
            
            throw new PaymentException(
                __('Transaction does not contain total amount.')
            );
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
        ];

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            ['increment_id' => $order->getIncrementId()]
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
