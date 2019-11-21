<?php

namespace Safecharge\Safecharge\Model;

use Magento\Customer\Model\Session\Proxy as CustomerSession;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\CcConfig;
use Magento\Payment\Model\CcGenericConfigProvider;
use Magento\Vault\Api\PaymentTokenManagementInterface;
//use Magento\Vault\Model\CreditCardTokenFactory;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;
//use Magento\Framework\App\Config\ScopeConfigInterface;

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
    
    private $apmsRequest;

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
        $this->moduleConfig				= $moduleConfig;
        $this->customerSession			= $customerSession;
        $this->paymentTokenManagement	= $paymentTokenManagement;
        $this->urlBuilder				= $urlBuilder;
        $this->requestFactory			= $requestFactory;

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
		
//        $customerId = $this->customerSession->getCustomerId();
//        $apmMethods = $this->getApmMethods();
        
        $objectManager  = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager   = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $locale			= $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        $cart		= $objectManager->get('\Magento\Checkout\Model\Cart');
//        $quote_id	= $cart->getQuote()->getId();
		
		$this->moduleConfig->createLog($cart->getQuote()->getTotals(), 'The totals:');
//		$this->moduleConfig->createLog($this->getApmsSessionToken(), 'SessionToken:');
        
        $config = [
            'payment' => [
                Payment::METHOD_CODE => [
                    'countryId'						=> $this->moduleConfig->getQuoteCountryCode(),
//                    'apmMethods'					=> $apmMethods,
                    'redirectUrl'					=> $this->urlBuilder->getUrl('safecharge/payment/redirect'),
                    'paymentApmUrl'					=> $this->urlBuilder->getUrl('safecharge/payment/apm'),
                    'getMerchantPaymentMethodsUrl'	=> $this->urlBuilder->getUrl('safecharge/payment/GetMerchantPaymentMethods'),
                    
                    // we need this for the WebSDK
                    'merchantSiteId'    => $this->moduleConfig->getMerchantSiteId(),
                    'merchantId'        => $this->moduleConfig->getMerchantId(),
                    'isTestMode'        => $this->moduleConfig->isTestModeEnabled(),
//                    'sessionToken'      => $this->getApmsSessionToken(),
                    'locale'            => substr($locale, 0, 2),
                    'total'             => (string) number_format($cart->getQuote()->getGrandTotal(), 2, '.', ''),
                    'currency'          => trim($storeManager->getStore()->getCurrentCurrencyCode()),
                ],
            ],
        ];

        return $config;
    }

    /**
     * Return AMP Methods.
     *
     * @return array
	 * @deprecated since version 2.0.0
     */
    private function getApmMethods()
    {
		$this->moduleConfig->createLog('requestFactory GET_MERCHANT_PAYMENT_METHODS_METHOD - ConfigProvider.php');
        $this->apmsRequest = $this->requestFactory->create(AbstractRequest::GET_MERCHANT_PAYMENT_METHODS_METHOD);

        try {
            $apmMethods = $this->apmsRequest ->process();
        }
		catch (PaymentException $e) {
            return [];
        }

        return $apmMethods->getPaymentMethods();
    }
    
    /**
     * Function getApmsSessionToken
     * The APMs must be got before we call this method
     * 
     * @return string Session Token (returned from the APMs) or empty
	 * @deprecated since version 2.0.0
     */
    private function getApmsSessionToken()
    {
        if(isset($this->apmsRequest)) {
            $class = $apmMethods = $this->apmsRequest ->process();
            
            return $class->getSessionToken();
        }
        
        return '';
    }
}
