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
	 * 
	 * @deprecated since 2.0.0
     */
    const MODE_LIVE     = 'live';
	/**
	 * @deprecated since 2.0.0
     */
    const MODE_SANDBOX  = 'sandbox';

    /**
     * Additional information const.
     */
    const KEY_CC_SAVE           = 'cc_save';
    const KEY_CC_TOKEN          = 'cc_token';
    const KEY_LAST_ST           = 'last_session_token';
    const KEY_CC_TEMP_TOKEN     = 'cc_temp_token';
    const KEY_CHOSEN_APM_METHOD = 'chosen_apm_method';

    /**
     * Transaction keys const.
     */
    const TRANSACTION_REQUEST_ID                = 'transaction_request_id';
    const TRANSACTION_ORDER_ID                  = 'safecharge_order_id';
    const TRANSACTION_AUTH_CODE_KEY             = 'authorization_code';
    const TRANSACTION_ID                        = 'transaction_id';
    const TRANSACTION_USER_PAYMENT_OPTION_ID    = 'user_payment_option_id';
    const TRANSACTION_SESSION_TOKEN             = 'session_token';
    const TRANSACTION_PAYMENT_SOLUTION          = 'payment_solution';
    const TRANSACTION_EXTERNAL_PAYMENT_METHOD   = 'external_payment_method';
    const TRANSACTION_STATUS					= 'sc_status';
    const TRANSACTION_TYPE						= 'sc_transaction_type';
    const REFUND_TRANSACTION_AMOUNT				= 'sc_refund_amount';
    const AUTH_PARAMS							= 'sc_auth_params';

    /**
     * Order statuses.
     */
    const SC_AUTH               = 'sc_auth';
    const SC_SETTLED            = 'sc_settled';
    const SC_PARTIALLY_SETTLED  = 'sc_partially_settled';
    const SC_VOIDED             = 'sc_voided';
    const SC_REFUNDED           = 'sc_refunded';
    const SC_PROCESSING         = 'sc_processing';

    const SOLUTION_INTERNAL		= 'internal';
    const SOLUTION_EXTERNAL		= 'external';
    const APM_METHOD_CC			= 'cc_card';

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
//    private $privateDataKeysProvider;

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
//        $this->privateDataKeysProvider = $privateDataKeysProvider;
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

        $ccToken = !empty($additionalData[self::KEY_CC_TOKEN])
            ? $additionalData[self::KEY_CC_TOKEN] : null;

        $chosenApmMethod = !empty($additionalData[self::KEY_CHOSEN_APM_METHOD])
            ? $additionalData[self::KEY_CHOSEN_APM_METHOD] : null;
        
        $lastSessionToken = !empty($additionalData[self::KEY_LAST_ST])
            ? $additionalData[self::KEY_LAST_ST] : null;

        $info = $this->getInfoInstance();
        $info->setAdditionalInformation(self::KEY_CC_TOKEN, $ccToken);
        $info->setAdditionalInformation(self::KEY_LAST_ST, $lastSessionToken);
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
        $authCode = $payment->getAdditionalInformation(self::TRANSACTION_AUTH_CODE_KEY);
        
        if ($authCode === null) {
            $payment->setIsTransactionPending(true); // TODO do we need this
            return $this;
        }

        $method = AbstractRequest::PAYMENT_SETTLE_METHOD;

        /** @var RequestInterface $request */
        $request = $this->paymentRequestFactory->create(
            $method,
            $payment,
            $amount
        );
        
        $response = $request->process();
        
        if ($authCode === null) {
            $this->checkoutSession->setRedirectUrl($response->getRedirectUrl());
        }

        return $this;
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
     * {inheritdoc}
     */
    public function getConfigInterface()
    {
        return $this;
    }
}
