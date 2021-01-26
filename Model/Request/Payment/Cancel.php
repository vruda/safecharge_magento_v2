<?php

namespace Nuvei\Payments\Model\Request\Payment;

use Magento\Framework\Exception\PaymentException;
use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Payment;
use Nuvei\Payments\Model\Request\AbstractPayment;
use Nuvei\Payments\Model\RequestInterface;

/**
 * Nuvei Payments void payment request model.
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
        $orderPayment			= $this->orderPayment;
		$ord_trans_addit_info	= $orderPayment->getAdditionalInformation(Payment::ORDER_TRANSACTIONS_DATA);
        $order					= $orderPayment->getOrder();
		$alowed_trans_data		= [];
		
		if(is_array($ord_trans_addit_info) && !empty($ord_trans_addit_info)) {
			foreach(array_reverse($ord_trans_addit_info) as $trans) {
				if(
					strtolower($trans[Payment::TRANSACTION_STATUS]) == 'approved'
					&& in_array(strtolower($trans[Payment::TRANSACTION_TYPE]), ['auth', 'settle', 'sale'])
				) {
					$alowed_trans_data = $trans;
					break;
				}
			}
		}
		
        if (empty($alowed_trans_data)) {
            $msg = 'Void Error - There is no approved Settle or Auth Transaction';
            $this->config->createLog(
				[
					'$ord_trans_addit_info'	=> $ord_trans_addit_info,
					'$alowed_trans_data'	=> $alowed_trans_data,
				],
				$msg
			);
            
            throw new PaymentException(
                __($msg)
            );
        }
        
		// Auth
		if ('auth' == $trans[Payment::TRANSACTION_TYPE]) { 
            $amount = $alowed_trans_data[Payment::TRANSACTION_TOTAL_AMOUN];
        } else { // Settle and Sale
			$amount = (float) $order->getTotalPaid();
        }

        if (empty($alowed_trans_data[Payment::TRANSACTION_AUTH_CODE])) {
			$msg = 'Void error: Transaction does not contain authorization code.';
			
            $this->config->createLog($alowed_trans_data, $msg);
            
            throw new PaymentException(__($msg));
        }
        
        if (empty($amount)) {
			$msg = 'Void error - Transaction does not contain total amount.';
			
            $this->config->createLog($alowed_trans_data, $msg);
            
            throw new PaymentException(__($msg));
        }
        
        $params = [
            'clientUniqueId'        => $order->getIncrementId(),
            'currency'              => $order->getBaseCurrencyCode(),
            'amount'                => $amount,
            'relatedTransactionId'  => $alowed_trans_data[Payment::TRANSACTION_ID],
            'authCode'              => $alowed_trans_data[Payment::TRANSACTION_AUTH_CODE],
            'comment'               => '',
            'merchant_unique_id'    => $order->getIncrementId(),
            'urlDetails'            => [
                'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
            ],
        ];

        $params = array_merge_recursive($params, parent::getParams());

//        $this->logger->updateRequest(
//            $this->getRequestId(),
//            ['increment_id' => $order->getIncrementId()]
//        );

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
