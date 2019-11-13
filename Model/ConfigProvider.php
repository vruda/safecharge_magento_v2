<?php

namespace Safecharge\Safecharge\Model;

use Magento\Customer\Model\Session\Proxy as CustomerSession;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\CcConfig;
use Magento\Payment\Model\CcGenericConfigProvider;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;

/**
 * Safecharge Safecharge config provider model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class ConfigProvider extends CcGenericConfigProvider
{
    /**
     * @var Config
     */
    private $moduleConfig;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var PaymentTokenManagementInterface
     */
    private $paymentTokenManagement;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * ConfigProvider constructor.
     *
     * @param CcConfig                        $ccConfig
     * @param PaymentHelper                   $paymentHelper
     * @param Config                          $moduleConfig
     * @param CustomerSession                 $customerSession
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param UrlInterface                    $urlBuilder
     * @param RequestFactory                  $requestFactory
     * @param array                           $methodCodes
     */
    public function __construct(
        CcConfig $ccConfig,
        PaymentHelper $paymentHelper,
        ModuleConfig $moduleConfig,
        CustomerSession $customerSession,
        PaymentTokenManagementInterface $paymentTokenManagement,
        UrlInterface $urlBuilder,
        RequestFactory $requestFactory,
        array $methodCodes
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->customerSession = $customerSession;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->urlBuilder = $urlBuilder;
        $this->requestFactory = $requestFactory;

        $methodCodes = array_merge_recursive(
            $methodCodes,
            [Payment::METHOD_CODE]
        );

        parent::__construct(
            $ccConfig,
            $paymentHelper,
            $methodCodes
        );
    }

    /**
     * Return config array.
     *
     * @return array
     */
    public function getConfig()
    {
        if (!$this->moduleConfig->isActive()) {
            return [];
        }

        $customerId = $this->customerSession->getCustomerId();

        $useVault = $customerId ? $this->moduleConfig->getUseVault() : false;
        $savedCards = $this->getSavedCards();
        $canSaveCard = $customerId ? true : false;

        $apmMethods = $this->getApmMethods();

        $config = [
            'payment' => [
                Payment::METHOD_CODE => [
                    'useVault' => $useVault,
                    'isCcDetectionEnabled' => $this->moduleConfig->getUseCcDetection(),
                    'useccv' => $this->moduleConfig->getUseCcv(),
                    'savedCards' => $savedCards,
                    'canSaveCard' => $canSaveCard,
                    'countryId' => $this->moduleConfig->getQuoteCountryCode(),
                    'apmMethods' => $apmMethods,
                    'is3dSecureEnabled' => $this->moduleConfig->is3dSecureEnabled(),
                    'authenticateUrl' => $this->urlBuilder->getUrl('safecharge/payment/authenticate'),
                    'externalSolution' => $this->moduleConfig->getPaymentSolution() === Payment::SOLUTION_EXTERNAL,
                    'redirectUrl' => $this->urlBuilder->getUrl('safecharge/payment/redirect'),
                    'paymentApmUrl' => $this->urlBuilder->getUrl('safecharge/payment/apm'),
                    'getMerchantPaymentMethodsUrl' => $this->urlBuilder->getUrl('safecharge/payment/GetMerchantPaymentMethods'),
                ],
            ],
        ];

        return $config;
    }

    /**
     * Return saved cards.
     *
     * @return array
     */
    private function getSavedCards()
    {
        $customerId = $this->customerSession->getCustomerId();
        if (!$customerId) {
            return [];
        }

        $savedCards = [];
        $ccTypes = $this->getCcAvailableTypes(Payment::METHOD_CODE);

        /** @var array $paymentTokens */
        $paymentTokens = $this->paymentTokenManagement->getListByCustomerId($customerId);

        foreach ($paymentTokens as $paymentToken) {
            if ($paymentToken->getType() !== CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD) {
                continue;
            }
            if ($paymentToken->getPaymentMethodCode() !== Payment::METHOD_CODE) {
                continue;
            }

            $cardDetails = json_decode($paymentToken->getDetails(), 1);

            $cardTypeName = isset($ccTypes[$cardDetails['cc_type']])
                ? $ccTypes[$cardDetails['cc_type']]
                : $cardDetails['cc_type'];

            $cardLabel = sprintf(
                '%s xxxx-%s %s/%s',
                $cardTypeName,
                $cardDetails['cc_last_4'],
                str_pad($cardDetails['cc_exp_month'], 2, 0, STR_PAD_LEFT),
                substr($cardDetails['cc_exp_year'], -2)
            );

            $savedCards[$paymentToken->getPublicHash()] = $cardLabel;
        }

        return $savedCards;
    }

    /**
     * Return AMP Methods.
     *
     * @return array
     */
    private function getApmMethods()
    {
        if ($this->moduleConfig->getPaymentSolution() === Payment::SOLUTION_EXTERNAL) {
            return [];
        }
        $request = $this->requestFactory->create(AbstractRequest::GET_MERCHANT_PAYMENT_METHODS_METHOD);

        try {
            $apmMethods = $request->process();
        } catch (PaymentException $e) {
            return [];
        }

        return $apmMethods->getPaymentMethods();
    }
}
