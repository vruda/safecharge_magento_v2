<?php

namespace Safecharge\Safecharge\Model\Response\Payment;

use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Response\AbstractPayment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge 3d secure payment response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Dynamic3D extends AbstractPayment implements ResponseInterface
{
    /**
     * @var int
     */
    protected $transactionId;

    /**
     * @var int
     */
    protected $threeDFlow;

    /**
     * @var string|null
     */
    protected $acsUrl;

    /**
     * @var int
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $paReq;

    /**
     * @var string|null
     */
    protected $userPaymentOptionId;

    /**
     * @var string
     */
    protected $authCode;

    /**
     * @return Dynamic3D
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->transactionId = $body['transactionId'];
        $this->threeDFlow = $body['threeDFlow'];
        $this->acsUrl = !empty($body['acsUrl']) ? $body['acsUrl'] : null;
        $this->orderId = $body['orderId'];
        $this->paReq = !empty($body['paRequest']) ? $body['paRequest'] : null;
        $this->userPaymentOptionId = !empty($body['userPaymentOptionId']) ? $body['userPaymentOptionId'] : null;
        $this->authCode = $body['authCode'];

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
     * @return Dynamic3D
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    protected function updateTransaction()
    {
        parent::updateTransaction();

        $this->orderPayment
            ->setTransactionId($this->getTransactionId())
            ->setIsTransactionPending(false)
            ->setIsTransactionClosed(0);

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
        if ($this->getAuthCode()) {
            $this->orderPayment->setAdditionalInformation(
                Payment::TRANSACTION_AUTH_CODE_KEY,
                $this->getAuthCode()
            );
        }
        if ($this->orderPayment->getCcLast4()) {
            $this->orderPayment->setAdditionalInformation(
                Payment::TRANSACTION_CARD_NUMBER,
                'XXXX-' . $this->orderPayment->getCcLast4()
            );
        }
        if ($this->orderPayment->getCcType()) {
            $this->orderPayment->setAdditionalInformation(
                Payment::TRANSACTION_CARD_TYPE,
                $this->orderPayment->getCcType()
            );
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @return int
     */
    public function getThreeDFlow()
    {
        return $this->threeDFlow;
    }

    /**
     * @return string
     */
    public function getAscUrl()
    {
        return $this->acsUrl;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @return string
     */
    public function getPaReq()
    {
        return $this->paReq;
    }

    /**
     * @return string|null
     */
    public function getUserPaymentOptionId()
    {
        return $this->userPaymentOptionId;
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
                'authCode',
                'orderId',
                'transactionId',
                'threeDFlow',
                'transactionStatus',
            ]
        );
    }
}
