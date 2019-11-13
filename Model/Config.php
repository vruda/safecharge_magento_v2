<?php

namespace Safecharge\Safecharge\Model;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Safecharge Safecharge config model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Config
{
    const MODULE_NAME = 'Safecharge_Safecharge';

    /**
     * Scope config object.
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Store manager object.
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Store id.
     *
     * @var int
     */
    private $storeId;

    /**
     * Already fetched config values.
     *
     * @var array
     */
    private $config = [];

    /**
     * Object initialization.
     *
     * @param ScopeConfigInterface  $scopeConfig Scope config object.
     * @param StoreManagerInterface $storeManager Store manager object.
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     * @param CheckoutSession $checkoutSession
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList,
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;

        $this->storeId = $this->getStoreId();
    }

    /**
     * Return config path.
     *
     * @return string
     */
    private function getConfigPath()
    {
        return sprintf('payment/%s/', Payment::METHOD_CODE);
    }

    /**
     * Return store manager.
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * Return store manager.
     * @return StoreManagerInterface
     */
    public function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * Return store id.
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Return config field value.
     *
     * @param string $fieldKey Field key.
     *
     * @return mixed
     */
    private function getConfigValue($fieldKey)
    {
        if (isset($this->config[$fieldKey]) === false) {
            $this->config[$fieldKey] = $this->scopeConfig->getValue(
                $this->getConfigPath() . $fieldKey,
                ScopeInterface::SCOPE_STORE,
                $this->storeId
            );
        }

        return $this->config[$fieldKey];
    }

    /**
     * Return bool value depends of that if payment method is active or not.
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool)$this->getConfigValue('active');
    }

    /**
     * Return title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigValue('title');
    }

    /**
     * Return merchant id.
     *
     * @return string
     */
    public function getMerchantId()
    {
        if ($this->isTestModeEnabled() === true) {
            return $this->getConfigValue('sandbox_merchant_id');
        }

        return $this->getConfigValue('merchant_id');
    }

    /**
     * Return merchant site id.
     *
     * @return string
     */
    public function getMerchantSiteId()
    {
        if ($this->isTestModeEnabled() === true) {
            return $this->getConfigValue('sandbox_merchant_site_id');
        }

        return $this->getConfigValue('merchant_site_id');
    }

    /**
     * Return merchant secret key.
     *
     * @return string
     */
    public function getMerchantSecretKey()
    {
        if ($this->isTestModeEnabled() === true) {
            return $this->getConfigValue('sandbox_merchant_secret_key');
        }

        return $this->getConfigValue('merchant_secret_key');
    }

    /**
     * Return enable cc detection configuration value.
     *
     * @return bool
     */
    public function getUseCcDetection()
    {
        return (bool)$this->getConfigValue('enable_cc_detection');
    }

    /**
     * Return bool value depends of that if 3d secure is enabled or not.
     *
     * @return bool
     */
    public function is3dSecureEnabled()
    {
        return (bool)$this->getConfigValue('secure_3d');
    }

    /**
     * Return payment action configuration value.
     *
     * @return string
     */
    public function getPaymentAction()
    {
        return $this->getConfigValue('payment_action');
    }

    /**
     * Return payment solution configuration value.
     *
     * @return string
     */
    public function getPaymentSolution()
    {
        return $this->getConfigValue('payment_solution');
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     */
    public function isTestModeEnabled()
    {
        if ($this->getConfigValue('mode') === Payment::MODE_LIVE) {
            return false;
        }

        return true;
    }

    /**
     * Return bool value depends of that if payment method debug mode
     * is enabled or not.
     *
     * @return bool
     */
    public function isDebugEnabled()
    {
        return (bool)$this->getConfigValue('debug');
    }

    /**
     * Return cc types.
     *
     * @return string
     */
    public function getCcTypes()
    {
        return $this->getConfigValue('cctypes');
    }

    /**
     * Return use vault configuration value.
     *
     * @return bool
     */
    public function getUseVault()
    {
        return (bool)$this->getConfigValue('use_vault');
    }

    /**
     * Return use ccv configuration value.
     *
     * @return bool
     */
    public function getUseCcv()
    {
        return (bool)$this->getConfigValue('useccv');
    }

    public function getSourcePlatformField()
    {
        return "{$this->productMetadata->getName()} {$this->productMetadata->getEdition()} {$this->productMetadata->getVersion()}, " . self::MODULE_NAME . "-{$this->moduleList->getOne(self::MODULE_NAME)['setup_version']}";
    }

    /**
     * Return full endpoint;
     *
     * @return string
     */
    public function getEndpoint()
    {
        $endpoint = AbstractRequest::LIVE_ENDPOINT;
        if ($this->isTestModeEnabled() === true) {
            $endpoint = AbstractRequest::TEST_ENDPOINT;
        }

        return $endpoint . 'purchase.do';
    }

    /**
     * @return string
     */
    public function getCallbackSuccessUrl()
    {
        $quoteId = $this->checkoutSession->getQuoteId();

        return $this->urlBuilder->getUrl(
            'safecharge/payment/callback_success',
            ['quote' => $quoteId]
        );
    }

    /**
     * @return string
     */
    public function getCallbackPendingUrl()
    {
        $quoteId = $this->checkoutSession->getQuoteId();

        return $this->urlBuilder->getUrl(
            'safecharge/payment/callback_pending',
            ['quote' => $quoteId]
        );
    }

    /**
     * @return string
     */
    public function getCallbackErrorUrl()
    {
        $quoteId = $this->checkoutSession->getQuoteId();

        return $this->urlBuilder->getUrl(
            'safecharge/payment/callback_error',
            ['quote' => $quoteId]
        );
    }

    /**
     * @return string
     */
    public function getCallbackDmnUrl($incrementId = null, $storeId = null)
    {
        return $this->getStoreManager()
            ->getStore((is_null($incrementId)) ? $this->storeId : $storeId)
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB) . 'safecharge/payment/callback_dmn/order/' . ((is_null($incrementId)) ? $this->getReservedOrderId() : $incrementId);
    }

    /**
     * @return string
     */
    public function getBackUrl()
    {
        return $this->urlBuilder->getUrl('checkout/cart');
    }

    public function getQuoteId()
    {
        return (($quote = $this->checkoutSession->getQuote())) ? $quote->getId() : null;
    }

    public function getReservedOrderId()
    {
        $reservedOrderId = $this->checkoutSession->getQuote()->getReservedOrderId();
        if (!$reservedOrderId) {
            $this->checkoutSession->getQuote()->reserveOrderId()->save();
            $reservedOrderId = $this->checkoutSession->getQuote()->getReservedOrderId();
        }
        return $reservedOrderId;
    }

    /**
     * Get default country code.
     * @return string
     */
    public function getDefaultCountry()
    {
        return $this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE, $this->storeId);
    }

    public function getQuoteCountryCode()
    {
        $quote = $this->checkoutSession->getQuote();
        $billing = ($quote) ? $quote->getBillingAddress() : null;
        $countryCode =  ($billing) ? $billing->getCountryId() : null;
        if (!$countryCode) {
            $shipping = ($quote) ? $quote->getShippingAddress() : null;
            $countryCode =  ($shipping && $shipping->getSameAsBilling()) ? $shipping->getCountryId() : null;
        }
        return $countryCode;
    }

    public function getQuoteBaseCurrency()
    {
        $quote = $this->checkoutSession->getQuote()->getBaseCurrencyCode();
    }
	
	public function createLog($data, $title = '')
	{
		$string = date('Y-m-d H:i:s') . "\r\n";
		
		if(!empty($title)) {
			$string .= $title . "\r\n";
		}
		
		if (!empty($data)) {
			if(is_array($data) or is_object($data)) {
				$string .= print_r($data, true);
			}
			elseif (is_bool($data)) {
				$string .= $data ? 'true' : 'false';
			}
			else {
				$string .= $data;
			}
		}
		else {
			$string .= 'Data is Empty.';
		}
		
		$string .= "\r\n" . "\r\n";
		
		try {
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');

			$logsPath = $directory->getPath('log');

			if(is_dir($logsPath)) {
				file_put_contents(
					$logsPath . DIRECTORY_SEPARATOR . date('Y-m-d') . '.txt',
					$string,
					FILE_APPEND
				);
			}	
		}
		catch(exception $e) {}
	}
}
