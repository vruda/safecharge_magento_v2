<?php

namespace Safecharge\Safecharge\Controller\Payment;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\OrderFactory;

/**
 * Safecharge Safecharge payment place controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Update extends Action
{
    /**
     * @var PaymentRequestFactory
     */
    private $paymentRequestFactory;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * Object constructor.
     *
     * @param Context               $context
     * @param PaymentRequestFactory $paymentRequestFactory
     * @param OrderFactory          $orderFactory
     * @param SafechargeLogger      $safechargeLogger
     * @param ModuleConfig          $moduleConfig
     */
    public function __construct(
        Context $context,
        PaymentRequestFactory $paymentRequestFactory,
        OrderFactory $orderFactory,
        SafechargeLogger $safechargeLogger,
        ModuleConfig $moduleConfig
    ) {
        parent::__construct($context);

        $this->paymentRequestFactory = $paymentRequestFactory;
        $this->orderFactory = $orderFactory;
        $this->safechargeLogger = $safechargeLogger;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @return ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();

        if ($this->moduleConfig->isDebugEnabled() === true) {
            $this->safechargeLogger->debug(
                'Redirect Update Response: '
                . json_encode($params)
            );
        }

        $orderId = $this->getRequest()->getParam('order');

        /** @var Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        /** @var OrderPayment $payment */
        $payment = $order->getPayment();

        $request = $this->paymentRequestFactory->create(
            AbstractRequest::PAYMENT_PAYMENT_3D_METHOD,
            $payment,
            $order->getBaseGrandTotal()
        );

        $userPaymentOptionId = $payment
            ->getAdditionalInformation(Payment::TRANSACTION_USER_PAYMENT_OPTION_ID);
        $cardCvv = $payment
            ->getAdditionalInformation(Payment::TRANSACTION_CARD_CVV);

        try {
            $request
                ->setUserPaymentOptionId($userPaymentOptionId)
                ->setCardCvv($cardCvv)
                ->setPaResponse(!empty($params['PaRes']) ? $params['PaRes'] : null)
                ->process();
        } catch (PaymentException $e) {
            $this->messageManager->addErrorMessage(
                __(
                    'Order has been placed but unfortunately payment has been not '
                    . 'authenticated properly. Details: "%1".',
                    $e->getMessage()
                )
            );
        }

        $payment->unsAdditionalInformation(Payment::TRANSACTION_USER_PAYMENT_OPTION_ID);
        $payment->save();

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/onepage/success/'));

        return $resultRedirect;
    }
}
