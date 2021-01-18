<?php

namespace Nuvei\Payments\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Payment;
use Nuvei\Payments\Model\Request\AbstractPayment;
use Nuvei\Payments\Model\RequestInterface;

/**
 * Nuvei Payments settle payment request model.
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
        $orderPayment	= $this->orderPayment;
        $order          = $orderPayment->getOrder();
        $auth_data      = $orderPayment->getAdditionalInformation(Payment::AUTH_PARAMS);
//		$nuvei_data		= $orderPayment->getAdditionalInformation('nuvei');
		
        if (empty($auth_data['AuthCode']) or empty($auth_data['TransactionID'])) {
            $this->config->createLog($auth_data, 'Missing Auth paramters!');
            
            throw new PaymentException(__('Missing Auth parameters.'));
        }
		
//		$invCollection	= $order->getInvoiceCollection();
//		$inv_ids		= [];
//		
//		if(!empty($invCollection) && is_array($invCollection)) {
//			foreach ($invCollection as $invoice) {
//				$inv_ids[] = $invoice->getId();
//			}
//		}
		
        $getIncrementId = $order->getIncrementId();

        $params = [
            'clientUniqueId'            => $getIncrementId,
            'amount'                    => (float)$this->amount,
            'currency'                    => $order->getBaseCurrencyCode(),
            'relatedTransactionId'        => $auth_data['TransactionID'],
            'authCode'                    => $auth_data['AuthCode'],
            'urlDetails'                => [
                'notificationUrl' => $this->config->getCallbackDmnUrl($getIncrementId),
            ],
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
