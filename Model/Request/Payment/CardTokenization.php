<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\AbstractPayment;
use Safecharge\Safecharge\Model\RequestInterface;

/**
 * Safecharge Safecharge card tokenization payment request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class CardTokenization extends AbstractPayment implements RequestInterface
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_CARD_TOKENIZATION_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_CC_TOKENIZATION_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $this->orderPayment;

        /** @var Order $order */
        $order = $orderPayment->getOrder();

        /** @var OrderAddressInterface $billing */
        $billing = $order->getBillingAddress();

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

        $params = [
            'sessionToken' => $tokenResponse->getToken(),
            'userTokenId' => $order->getCustomerId(),
            'cardData' => [
                'cardNumber' => $orderPayment->getCcNumber(),
                'cardHolderName' => $orderPayment->getCcOwner(),
                'expirationMonth' => $orderPayment->getCcExpMonth(),
                'expirationYear' => $orderPayment->getCcExpYear(),
                'CVV' => $orderPayment->getCcCid(),
            ],
            'billingAddress' => [
                'firstName' => $billing->getFirstname(),
                'lastName' => $billing->getLastname(),
                'address' => is_array($billing->getStreet())
                    ? implode(' ', $billing->getStreet())
                    : '',
                'cell' => '',
                'phone' => $billing->getTelephone(),
                'zip' => $billing->getPostcode(),
                'city' => $billing->getCity(),
                'country' => $billing->getCountryId(),
                'state' => $billing->getRegionCode(),
                'email' => $billing->getEmail(),
            ],
            'merchant_unique_id' => $order->getIncrementId(),
            'urlDetails' => [
                'notificationUrl' => $this->config->getCallbackDmnUrl($order->getIncrementId(), $order->getStoreId()),
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
        return [];
    }
}
