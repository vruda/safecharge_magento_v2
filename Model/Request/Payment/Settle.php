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
        $orderPayment		= $this->orderPayment;
        $order				= $orderPayment->getOrder();
		$auth_data			= $orderPayment->getAdditionalInformation(Payment::AUTH_PARAMS);
		
        if (empty($auth_data['AuthCode']) or empty($auth_data['TransactionID'])) {
			$this->config->createLog($auth_data, 'Missing Auth paramters!');
			
            throw new PaymentException(__('Missing Auth parameters.'));
        }
		
		$getIncrementId = $order->getIncrementId();

        $params = [
            'clientUniqueId'			=> $getIncrementId,
			'amount'					=> (float)$this->amount,
            'currency'					=> $order->getBaseCurrencyCode(),
            'relatedTransactionId'		=> $auth_data['TransactionID'],
			'authCode'					=> $auth_data['AuthCode'],
			'urlDetails'				=> [
                'notificationUrl' => $this->config->getCallbackDmnUrl($getIncrementId),
            ],
			'sourceApplication'			=> $this->config->getSourceApplication(),
        ];

        $params = array_merge_recursive(parent::getParams(), $params);

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
