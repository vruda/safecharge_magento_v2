<?php

namespace Safecharge\Safecharge\Model;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Customer\Model\Session\Proxy as CustomerSession;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Registry as CoreRegistry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Cc;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use Magento\Payment\Model\Method\TransparentInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Safecharge\Safecharge\Model\Response\Payment\Dynamic3D as Dynamic3DResponse;

/**
 * Safecharge Safecharge payment model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Payment extends Cc implements TransparentInterface
{
    /**
     * Method code const.
     */
    const METHOD_CODE = 'safecharge';

    /**
     * Method modes.
     */
    const MODE_LIVE = 'live';
    const MODE_SANDBOX = 'sandbox';

    /**
     * Method vault code const.
     */
    const CC_VAULT_CODE = 'safecharge_vault';

    /**
     * Additional information const.
     */
    const KEY_CC_SAVE = 'cc_save';
    const KEY_CC_TOKEN = 'cc_token';
    const KEY_CC_TEMP_TOKEN = 'cc_temp_token';
    const KEY_CHOSEN_APM_METHOD = 'chosen_apm_method';

    /**
     * Transaction keys const.
     */
    const TRANSACTION_REQUEST_ID = 'transaction_request_id';
    const TRANSACTION_ORDER_ID = 'safecharge_order_id';
    const TRANSACTION_AUTH_CODE_KEY = 'authorization_code';
    const TRANSACTION_ID = 'transaction_id';
    const TRANSACTION_CARD_NUMBER = 'card_number';
    const TRANSACTION_CARD_TYPE = 'card_type';
    const TRANSACTION_USER_PAYMENT_OPTION_ID = 'user_payment_option_id';
    const TRANSACTION_SESSION_TOKEN = 'session_token';
    const TRANSACTION_CARD_CVV = 'card_cvv';
    const TRANSACTION_PAYMENT_SOLUTION = 'payment_solution';
    const TRANSACTION_EXTERNAL_PAYMENT_METHOD = 'external_payment_method';

    /**
     * Order statuses.
     */
    const SC_AUTH = 'sc_auth';
    const SC_SETTLED = 'sc_settled';
    const SC_PARTIALLY_SETTLED = 'sc_partially_settled';
    const SC_VOIDED = 'sc_voided';

    /**
     * Payment solutions.
     */
    const SOLUTION_INTERNAL = 'internal';
    const SOLUTION_EXTERNAL = 'external';

    const APM_METHOD_CC = 'cc_card';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * Form block.
     *
     * @var string
     */
    protected $_formBlockType = \Magento\Payment\Block\Transparent\Info::class;

    /**
     * Info block.
     *
     * @var string
     */
    protected $_infoBlockType = \Safecharge\Safecharge\Block\ConfigurableInfo::class;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_isInitializeNeeded = false;

    /**
     * @var PaymentRequestFactory
     */
    private $paymentRequestFactory;

    /**
     * @var PaymentTokenManagementInterface
     */
    private $paymentTokenManagement;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var PrivateDataKeysProvider
     */
    private $privateDataKeysProvider;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * Payment constructor.
     *
     * @param Context                         $context
     * @param CoreRegistry                    $registry
     * @param ExtensionAttributesFactory      $extensionFactory
     * @param AttributeValueFactory           $customAttributeFactory
     * @param Data                            $paymentData
     * @param ScopeConfigInterface            $scopeConfig
     * @param PaymentLogger                   $logger
     * @param ModuleListInterface             $moduleList
     * @param TimezoneInterface               $localeDate
     * @param PaymentRequestFactory           $paymentRequestFactory
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param CustomerSession                 $customerSession
     * @param ModuleConfig                    $moduleConfig
     * @param PrivateDataKeysProvider         $privateDataKeysProvider
     * @param CheckoutSession                 $checkoutSession
     * @param AbstractResource|null           $resource
     * @param AbstractDb|null                 $resourceCollection
     * @param array                           $data
     */
    public function __construct(
        Context $context,
        CoreRegistry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        PaymentLogger $logger,
        ModuleListInterface $moduleList,
        TimezoneInterface $localeDate,
        PaymentRequestFactory $paymentRequestFactory,
        PaymentTokenManagementInterface $paymentTokenManagement,
        CustomerSession $customerSession,
        ModuleConfig $moduleConfig,
        PrivateDataKeysProvider $privateDataKeysProvider,
        CheckoutSession $checkoutSession,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            $resource,
            $resourceCollection,
            $data
        );

        $this->paymentRequestFactory = $paymentRequestFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->customerSession = $customerSession;
        $this->moduleConfig = $moduleConfig;
        $this->privateDataKeysProvider = $privateDataKeysProvider;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Assign data.
     *
     * @param DataObject $data Data object.
     *
     * @return Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(DataObject $data)
    {
        parent::assignData($data);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        $ccSave = !empty($additionalData[self::KEY_CC_SAVE])
            ? (bool)$additionalData[self::KEY_CC_SAVE]
            : false;

        $ccToken = !empty($additionalData[self::KEY_CC_TOKEN])
            ? $additionalData[self::KEY_CC_TOKEN]
            : null;

        $chosenApmMethod = !empty($additionalData[self::KEY_CHOSEN_APM_METHOD])
            ? $additionalData[self::KEY_CHOSEN_APM_METHOD]
            : null;

        if ($ccToken !== null) {
            $ccSave = false;
        }

        $info = $this->getInfoInstance();
        $info->setAdditionalInformation(self::KEY_CC_SAVE, $ccSave);
        $info->setAdditionalInformation(self::KEY_CC_TOKEN, $ccToken);
        $info->setAdditionalInformation(self::KEY_CHOSEN_APM_METHOD, $chosenApmMethod);

        return $this;
    }

    /**
     * Validate payment method information object.
     *
     * @return Payment
     * @throws LocalizedException
     */
    public function validate()
    {
        $paymentSolution = $this->moduleConfig->getPaymentSolution();
        $info = $this->getInfoInstance();

        if ($paymentSolution === self::SOLUTION_EXTERNAL || ($info->getAdditionalInformation(self::KEY_CHOSEN_APM_METHOD) !== self::APM_METHOD_CC)) {
            return $this;
        }

        $tokenHash = $info->getAdditionalInformation(self::KEY_CC_TOKEN);

        if ($tokenHash === null) {
            parent::validate();

            return $this;
        }

        if ($this->hasVerification()) {
            $customerId = $this->customerSession->getCustomerId();

            $token = $this->paymentTokenManagement
                ->getByPublicHash($tokenHash, $customerId);
            if ($token->getId() === null) {
                $info->setAdditionalInformation(self::KEY_CC_TOKEN, null);

                return $this;
            }

            $tokenDetails = json_decode($token->getTokenDetails(), 1);
            $cardType = $tokenDetails['cc_type'];

            $verificationRegEx = $this->getVerificationRegEx();

            $regExp = isset($verificationRegEx[$cardType])
                ? $verificationRegEx[$cardType]
                : '';

            if (!$regExp
                || !$info->getCcCid()
                || !preg_match($regExp, $info->getCcCid())
            ) {
                throw new LocalizedException(
                    __('Please enter a valid credit card verification number.')
                );
            }
        }

        return $this;
    }

    /**
     * Check if payment method can be used for provided currency.
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        return true;
    }

    /**
     * Authorize payment method.
     *
     * @param InfoInterface $payment
     * @param float         $amount
     *
     * @return Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @api
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        parent::authorize($payment, $amount);

        $this->processPayment($payment, $amount);

        return $this;
    }

    /**
     * Capture payment method.
     *
     * @param InfoInterface $payment
     * @param float         $amount
     *
     * @return Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @api
     */
    public function capture(InfoInterface $payment, $amount)
    {
        parent::capture($payment, $amount);

        $this->processPayment($payment, $amount);

        return $this;
    }

    private function processPayment(InfoInterface $payment, $amount)
    {
        $this->checkoutSession
            ->unsAscUrl()
            ->unsPaReq();

        $paymentSolution = $this->moduleConfig->getPaymentSolution();
        $payment->setAdditionalInformation(
            self::TRANSACTION_PAYMENT_SOLUTION,
            $paymentSolution
        );

        $authCode = $payment->getAdditionalInformation(self::TRANSACTION_AUTH_CODE_KEY);

        if (($authCode === null && $paymentSolution === self::SOLUTION_EXTERNAL) || ($paymentSolution === self::SOLUTION_INTERNAL && $this->moduleConfig->getPaymentAction() === self::ACTION_AUTHORIZE_CAPTURE && ($chosenApmMethod = $payment->getAdditionalInformation(self::KEY_CHOSEN_APM_METHOD)) && $chosenApmMethod !== self::APM_METHOD_CC)) {
            $payment->setIsTransactionPending(true);
            return $this;
        }

        if ($authCode === null) {
            $secure3d = $this->moduleConfig->is3dSecureEnabled();
            if ($secure3d === true) {
                $method = AbstractRequest::PAYMENT_DYNAMIC_3D_METHOD;
            } else {
                $method = AbstractRequest::PAYMENT_CC_METHOD;
            }
        } else {
            $method = AbstractRequest::PAYMENT_SETTLE_METHOD;
        }

        /** @var RequestInterface $request */
        $request = $this->paymentRequestFactory->create(
            $method,
            $payment,
            $amount
        );
        $response = $request->process();

        if ($authCode === null && $paymentSolution === self::SOLUTION_EXTERNAL) {
            $this->checkoutSession
                ->setRedirectUrl($response->getRedirectUrl());
        } elseif ($method === AbstractRequest::PAYMENT_DYNAMIC_3D_METHOD) {
            $this->finalize3dSecurePayment($response, $payment, $amount);
        }

        return $this;
    }

    /**
     * @param Dynamic3DResponse $response
     * @param InfoInterface     $payment
     * @param float             $amount
     *
     * @return Payment
     * @throws \RuntimeException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\PaymentException
     */
    private function finalize3dSecurePayment(
        Dynamic3DResponse $response,
        InfoInterface $payment,
        $amount
    ) {
        $threeDFlow = (int)$response->getThreeDFlow();
        $ascUrl = $response->getAscUrl();

        if ($threeDFlow === 0 && $ascUrl === null) {
            /**
             * If the merchant’s configured mode of operation is sale,
             * then no further action is required.
             * If the merchant’s configured mode of operation is auth-settle,
             * then the merchant should call settleTransaction method afterwards.
             */
            if ($this->moduleConfig->getPaymentAction() === self::ACTION_AUTHORIZE_CAPTURE) {
                $request = $this->paymentRequestFactory->create(
                    AbstractRequest::PAYMENT_SETTLE_METHOD,
                    $payment,
                    $amount
                );
                $request->process();
            }

            return $this;
        }

        if ($threeDFlow === 1 && $ascUrl === null) {
            /**
             * The performed transaction will be 'sale’,
             * in order to complete the 'auth3D’ transaction
             * previously performed in dynamic3D method.
             */
            $request = $this->paymentRequestFactory->create(
                AbstractRequest::PAYMENT_PAYMENT_3D_METHOD,
                $payment,
                $amount
            );
            $request->process();

            return $this;
        }

        if ($threeDFlow === 1 && $ascUrl !== null) {
            /**
             * 1. Merchant should redirect to acsUrl.
             * 2. Merchant should call payment3D method afterwards.
             */
            $this->checkoutSession
                ->setPaReq($response->getPaReq())
                ->setAscUrl($ascUrl);

            $payment->setIsTransactionPending(true);

            $payment->setAdditionalInformation(
                self::TRANSACTION_USER_PAYMENT_OPTION_ID,
                $response->getUserPaymentOptionId()
            );
            $payment->setAdditionalInformation(
                self::TRANSACTION_CARD_CVV,
                $payment->getCcCid()
            );

            return $this;
        }

        throw new PaymentException(
            __('Unexpected response during 3d secure payment handling.')
        );
    }

    /**
     * Refund payment method.
     *
     * @param InfoInterface $payment
     * @param float         $amount
     *
     * @return Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @api
     */
    public function refund(InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        /** @var RequestInterface $request */
        $request = $this->paymentRequestFactory->create(
            AbstractRequest::PAYMENT_REFUND_METHOD,
            $payment,
            $amount
        );
        $request->process();

        return $this;
    }

    /**
     * Cancel payment method.
     *
     * @param InfoInterface $payment
     *
     * @return Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @api
     */
    public function cancel(InfoInterface $payment)
    {
        parent::cancel($payment);

        $this->void($payment);

        return $this;
    }

    /**
     * Refund payment method.
     *
     * @param InfoInterface $payment
     *
     * @return Payment
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @api
     */
    public function void(InfoInterface $payment)
    {
        parent::void($payment);

        /** @var RequestInterface $request */
        $request = $this->paymentRequestFactory->create(
            AbstractRequest::PAYMENT_VOID_METHOD,
            $payment
        );
        $request->process();

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getDebugReplacePrivateDataKeys()
    {
        return array_merge_recursive(
            parent::getDebugReplacePrivateDataKeys(),
            $this->privateDataKeysProvider->getConfig()
        );
    }

    /**
     * {inheritdoc}
     */
    public function getConfigInterface()
    {
        return $this;
    }
}
