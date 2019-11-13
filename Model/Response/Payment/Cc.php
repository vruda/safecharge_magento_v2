<?php

namespace Safecharge\Safecharge\Model\Response\Payment;

use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Response\AbstractPayment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge payment cc response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Cc extends AbstractPayment implements ResponseInterface
{
    /**
     * @var int
     */
    protected $orderId;

    /**
     * @var int
     */
    protected $transactionId;

    /**
     * @var string
     */
    protected $authCode;

    /**
     * @return Cc
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->orderId = $body['orderId'];
        $this->transactionId = $body['transactionId'];
        $this->authCode = $body['authCode'];

        return $this;
    }

    /**
     * @return Cc
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function updateTransaction()
    {
        parent::updateTransaction();

        $this->orderPayment->setAdditionalInformation(
            Payment::TRANSACTION_ID,
            $this->getTransactionId()
        );
        $this->orderPayment->setAdditionalInformation(
            Payment::TRANSACTION_REQUEST_ID,
            $this->getRequestId()
        );
        $this->orderPayment->setAdditionalInformation(
            Payment::TRANSACTION_ORDER_ID,
            $this->getOrderId()
        );
        $this->orderPayment->setAdditionalInformation(
            Payment::TRANSACTION_AUTH_CODE_KEY,
            $this->getAuthCode()
        );
        $this->orderPayment->setAdditionalInformation(
            Payment::TRANSACTION_CARD_NUMBER,
            'XXXX-' . $this->orderPayment->getCcLast4()
        );
        $this->orderPayment->setAdditionalInformation(
            Payment::TRANSACTION_CARD_TYPE,
            $this->orderPayment->getCcType()
        );

        $isSettled = false;
        if ($this->config->getPaymentAction() === Payment::ACTION_AUTHORIZE_CAPTURE) {
            $isSettled = true;
        }

        $this->orderPayment
            ->setTransactionId($this->getTransactionId())
            ->setIsTransactionClosed($isSettled ? 1 : 0);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    protected function getRequestStatus()
    {
        if (parent::getRequestStatus() === false) {
            return false;
        }

        $body = $this->getBody();
        if (strtolower($body['transactionStatus']) === 'error') {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @return int
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @return string
     */
    public function getAuthCode()
    {
        return $this->authCode;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return array_merge_recursive(
            parent::getRequiredResponseDataKeys(),
            [
                'orderId',
                'transactionId',
                'authCode',
                'transactionStatus',
            ]
        );
    }
}
