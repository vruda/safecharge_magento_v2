<?php

namespace Safecharge\Safecharge\Model\Response\Payment;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Order\Payment\Transaction;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Response\AbstractPayment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge 3d secure payment response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Payment3D extends AbstractPayment implements ResponseInterface
{
    /**
     * @var AuthorizeCommand
     */
    protected $authorizeCommand;

    /**
     * @var CaptureCommand
     */
    protected $captureCommand;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

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
     * Payment3D constructor.
     *
     * @param SafechargeLogger  $safechargeLogger
     * @param Config            $config
     * @param int               $requestId
     * @param Curl              $curl
     * @param AuthorizeCommand  $authorizeCommand
     * @param CaptureCommand    $captureCommand
     * @param CheckoutSession   $checkoutSession
     * @param OrderPayment|null $orderPayment
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        $requestId,
        Curl $curl,
        AuthorizeCommand $authorizeCommand,
        CaptureCommand $captureCommand,
        CheckoutSession $checkoutSession,
        $orderPayment
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $requestId,
            $curl,
            $orderPayment
        );

        $this->authorizeCommand = $authorizeCommand;
        $this->captureCommand = $captureCommand;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return Payment3D
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
     * @return Payment3D
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    protected function updateTransaction()
    {
        parent::updateTransaction();

        $isSettled = false;
        if ($this->config->getPaymentAction() === Payment::ACTION_AUTHORIZE_CAPTURE) {
            $isSettled = true;
        }

        /** @var Order $order */
        $order = $this->orderPayment->getOrder();

        if ($isSettled) {
            $message = $this->captureCommand->execute(
                $this->orderPayment,
                $order->getBaseGrandTotal(),
                $order
            );
            $transactionType = Transaction::TYPE_CAPTURE;
        } else {
            $message = $this->authorizeCommand->execute(
                $this->orderPayment,
                $order->getBaseGrandTotal(),
                $order
            );
            $transactionType = Transaction::TYPE_AUTH;
        }

        if ($this->orderPayment->getLastTransId()) {
            $this->orderPayment
                ->setParentTransactionId($this->orderPayment->getLastTransId());
        }

        $this->orderPayment
            ->setTransactionId($this->getTransactionId())
            ->setIsTransactionPending(false)
            ->setIsTransactionClosed($isSettled ? 1 : 0);

        $this->orderPayment
            ->unsAdditionalInformation(Payment::TRANSACTION_USER_PAYMENT_OPTION_ID);
        $this->orderPayment
            ->unsAdditionalInformation(Payment::TRANSACTION_CARD_CVV);

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

        if ($this->checkoutSession->getAscUrl()) {
            if ($this->config->getPaymentAction() === Payment::ACTION_AUTHORIZE_CAPTURE) {
                /** @var Invoice $invoice */
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoice
                        ->pay()
                        ->save();
                }
            }
            $transaction = $this->orderPayment->addTransaction($transactionType);

            $message = $this->orderPayment->prependMessage($message);
            $this->orderPayment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );

            $this->orderPayment->save();
            $order->save();
        }

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
