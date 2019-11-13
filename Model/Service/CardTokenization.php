<?php

namespace Safecharge\Safecharge\Model\Service;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\Data\PaymentTokenInterfaceFactory;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

/**
 * Safecharge Safecharge payment card tokenization service model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class CardTokenization
{
    /**
     * @var PaymentTokenInterfaceFactory
     */
    private $paymentTokenFactory;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var PaymentRequestFactory
     */
    private $paymentRequestFactory;

    /**
     * @var OrderPayment
     */
    private $orderPayment;

    /**
     * CardTokenization constructor.
     *
     * @param PaymentTokenInterfaceFactory    $paymentTokenFactory
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param PaymentRequestFactory           $paymentRequestFactory
     */
    public function __construct(
        PaymentTokenInterfaceFactory $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        PaymentRequestFactory $paymentRequestFactory
    ) {
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->paymentRequestFactory = $paymentRequestFactory;
    }

    /**
     * @param OrderPayment $orderPayment
     *
     * @return CardTokenization
     */
    public function setOrderPayment(OrderPayment $orderPayment)
    {
        $this->orderPayment = $orderPayment;

        return $this;
    }

    /**
     * @return PaymentTokenInterface
     * @throws LocalizedException
     */
    public function processCardPaymentToken()
    {
        if ($this->orderPayment === null) {
            throw new LocalizedException(
                __('Order payment object has been not set.')
            );
        }

        $ccTokenizeRequest = $this->paymentRequestFactory
            ->create(
                AbstractRequest::PAYMENT_CARD_TOKENIZATION_METHOD,
                $this->orderPayment
            );
        $ccTokenizeResponse = $ccTokenizeRequest->process();

        $this->orderPayment->setAdditionalInformation(
            Payment::KEY_CC_TEMP_TOKEN,
            $ccTokenizeResponse->getCcTempToken()
        );

        $userPaymentOptionRequest = $this->paymentRequestFactory
            ->create(
                AbstractRequest::PAYMENT_USER_PAYMENT_OPTION_METHOD,
                $this->orderPayment
            );
        $userPaymentOptionResponse = $userPaymentOptionRequest->process();

        $paymentTokenDetails = [
            'cc_type' => $this->orderPayment->getCcType(),
            'cc_last_4' => $this->orderPayment->getCcLast4(),
            'cc_exp_year' => $this->orderPayment->getCcExpYear(),
            'cc_exp_month' => $this->orderPayment->getCcExpMonth(),
        ];
        $paymentTokenHash = md5(
            implode('', $paymentTokenDetails)
            . $this->orderPayment->getOrder()->getCustomerId()
            . Payment::METHOD_CODE
        );

        $paymentToken = $this->paymentTokenFactory->create();
        $paymentToken
            ->setCustomerId($this->orderPayment->getOrder()->getCustomerId())
            ->setPublicHash($paymentTokenHash)
            ->setPaymentMethodCode(Payment::METHOD_CODE)
            ->setGatewayToken($userPaymentOptionResponse->getCcToken())
            ->setTokenDetails(json_encode($paymentTokenDetails))
            ->setExpiresAt($this->getExpirationDate())
            ->setIsActive(1)
            ->setIsVisible(1);

        $this->paymentTokenRepository->save($paymentToken);

        return $paymentToken;
    }

    /**
     * @return string
     */
    private function getExpirationDate()
    {
        $expDate = new \DateTime(
            $this->orderPayment->getCcExpYear()
            . '-'
            . $this->orderPayment->getCcExpMonth()
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );
        $expDate->add(new \DateInterval('P1M'));

        return $expDate->format('Y-m-d 00:00:00');
    }
}
