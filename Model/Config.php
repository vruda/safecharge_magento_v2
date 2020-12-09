<?php

namespace Safecharge\Safecharge\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Safecharge Safecharge config model.
 */
class Config
{
    const MODULE_NAME						= 'Safecharge_Safecharge';
	
    const PAYMENT_PLANS_ATTR_NAME			= 'safecharge_payment_plans';
    const PAYMENT_PLANS_ATTR_LABEL			= 'Nuvei Payment Plans';
	const PAYMENT_PLANS_FILE_NAME			= 'nuvei_payment_plans.json';
	
	const PAYMENT_SUBS_GROUP				= 'Nuvei Subscription';
    const PAYMENT_SUBS_ENABLE_LABEL			= 'Enable Subscription';
    const PAYMENT_SUBS_ENABLE				= 'safecharge_sub_enabled';
    const PAYMENT_SUBS_INTIT_AMOUNT_LABEL	= 'Initial Amount';
    const PAYMENT_SUBS_INTIT_AMOUNT			= 'safecharge_sub_init_amount';
	const PAYMENT_SUBS_REC_AMOUNT_LABEL		= 'Recurring Amount';
	const PAYMENT_SUBS_REC_AMOUNT			= 'safecharge_sub_rec_amount';
	
	const PAYMENT_SUBS_RECURR_UNITS			= 'safecharge_sub_recurr_units';
	const PAYMENT_SUBS_RECURR_UNITS_LABEL	= 'Recurring Units';
	const PAYMENT_SUBS_RECURR_PERIOD		= 'safecharge_sub_recurr_period';
	const PAYMENT_SUBS_RECURR_PERIOD_LABEL	= 'Recurring Period';
	
	const PAYMENT_SUBS_TRIAL_UNITS			= 'safecharge_sub_trial_units';
	const PAYMENT_SUBS_TRIAL_UNITS_LABEL	= 'Trial Units';
	const PAYMENT_SUBS_TRIAL_PERIOD			= 'safecharge_sub_trial_period';
	const PAYMENT_SUBS_TRIAL_PERIOD_LABEL	= 'Trial Period';
	
	const PAYMENT_SUBS_END_AFTER_UNITS			= 'safecharge_sub_end_after_units';
	const PAYMENT_SUBS_END_AFTER_UNITS_LABEL	= 'End After Units';
	const PAYMENT_SUBS_END_AFTER_PERIOD			= 'safecharge_sub_end_after_period';
	const PAYMENT_SUBS_END_AFTER_PERIOD_LABEL	= 'End After Period';
	
	const PAYMENT_SUBS_STORE_DESCR			= 'safecharge_sub_store_decr';
	const PAYMENT_SUBS_STORE_DESCR_LABEL	= 'Subscription details';
    
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
     * Magento version like integer.
     *
     * @var int
     */
    private $versionNum = '';
    
    /**
     * Use it to validate the redirect
     *
     * @var FormKey
     */
    private $formKey;
    
    private $directory;
    private $httpHeader;
    private $remoteIp;

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
        UrlInterface $urlBuilder,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Framework\Filesystem\DirectoryList $directory,
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteIp
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
        $this->httpHeader = $httpHeader;
        $this->remoteIp = $remoteIp;

        $this->storeId        = $this->getStoreId();
        $this->versionNum    = intval(str_replace('.', '', $this->productMetadata->getVersion()));
        $this->formKey        = $formKey;
        $this->directory    = $directory;
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
    
    public function createLog($data, $title = '')
    {
        if (! $this->isDebugEnabled()) {
            return;
        }
		
		$d		= $data;
		$string	= '';
		
        if (!empty($data)) {
            if (is_array($data)) {
				// do not log accounts if on prod
				if (!$this->isTestModeEnabled()) {
					if (isset($data['userAccountDetails']) && is_array($data['userAccountDetails'])) {
						$data['userAccountDetails'] = 'account details';
					}
					if (isset($data['userPaymentOption']) && is_array($data['userPaymentOption'])) {
						$data['userPaymentOption'] = 'user payment options details';
					}
					if (isset($data['paymentOption']) && is_array($data['paymentOption'])) {
						$data['paymentOption'] = 'payment options details';
					}
				}
				// do not log accounts if on prod
				
				if (!empty($data['paymentMethods']) && is_array($data['paymentMethods'])) {
					$data['paymentMethods'] = json_encode($data['paymentMethods']);
				}
				
				if (!empty($data['plans']) && is_array($data['plans'])) {
                    $data['plans'] = json_encode($data['plans']);
                }

				$d = $this->isTestModeEnabled() ? print_r($data, true) : json_encode($data);
            } 
			elseif(is_object($data)) {
				$d = $this->isTestModeEnabled() ? print_r($data, true) : json_encode($data);
			}
			elseif (is_bool($data)) {
                $d = $data ? 'true' : 'false';
            }
        } else {
            $string .= 'Data is Empty.';
        }
		
		$string .= '[v.' . $this->moduleList->getOne(self::MODULE_NAME)['setup_version'] . '] | ';
		
		if (!empty($title)) {
			if (is_string($title)) {
				$string .= $title;
			} else {
				$string .= "\r\n" . ( $this->isTestModeEnabled()
					? json_encode($title, JSON_PRETTY_PRINT) : json_encode($title) );
			}
			
			$string .= "\r\n";
		}

		$string .= $d . "\r\n\r\n";
		
		if($this->isDebugEnabled(true) == 1) {
			$log_file_name = 'Nuvei';
		}
		else {
			$log_file_name = 'Nuvei-' . date('Y-m-d');
		}
        
        try {
            $logsPath = $this->directory->getPath('log');

            if (is_dir($logsPath)) {
                file_put_contents(
                    $logsPath . DIRECTORY_SEPARATOR . $log_file_name . '.txt',
                    date('H:i:s', time()) . ': ' . $string,
                    FILE_APPEND
                );
            }
        } catch (exception $e) {

        }
    }
    
    public function getTempPath()
    {
        return $this->directory->getPath('tmp');
    }
    
    /**
     * Function getSourceApplication
     * Get the value of one more parameter for the REST API
     *
     * @return string
     */
    public function getSourceApplication()
    {
        return 'MAGENTO_2_PLUGIN';
    }

    /**
     * Function get_device_details
     * Get browser and device based on HTTP_USER_AGENT.
     * The method is based on D3D payment needs.
     *
     * @return array $device_details
     */
    public function getDeviceDetails()
    {
        $SC_DEVICES            = ['iphone', 'ipad', 'android', 'silk', 'blackberry', 'touch', 'linux', 'windows', 'mac'];
        $SC_BROWSERS        = ['ucbrowser', 'firefox', 'chrome', 'opera', 'msie', 'edge', 'safari', 'blackberry', 'trident'];
        $SC_DEVICES_TYPES    = ['macintosh', 'tablet', 'mobile', 'tv', 'windows', 'linux', 'tv', 'smarttv', 'googletv', 'appletv', 'hbbtv', 'pov_tv', 'netcast.tv', 'bluray'];
        $SC_DEVICES_OS        = ['android', 'windows', 'linux', 'mac os'];
        
        $device_details = [
            'deviceType'    => 'UNKNOWN', // DESKTOP, SMARTPHONE, TABLET, TV, and UNKNOWN
            'deviceName'    => '',
            'deviceOS'      => '',
            'browser'       => '',
            'ipAddress'     => '',
        ];
        
        // get ip
        try {
            $device_details['ipAddress']    = (string) $this->remoteIp->getRemoteAddress();
            $ua                                = $this->httpHeader->getHttpUserAgent();
        } catch (Exception $ex) {
            $this->createLog($e->getMessage(), 'getDeviceDetails Exception');
            return $device_details;
        }
        
        if (empty($ua)) {
            return $device_details;
        }
        
        $user_agent = strtolower($ua);
        $device_details['deviceName'] = $ua;

        foreach ($SC_DEVICES_TYPES as $d) {
            if (strstr($user_agent, $d) !== false) {
                if (in_array($d, ['linux', 'windows', 'macintosh'], true)) {
                    $device_details['deviceType'] = 'DESKTOP';
                } elseif ('mobile' === $d) {
                    $device_details['deviceType'] = 'SMARTPHONE';
                } elseif ('tablet' === $d) {
                    $device_details['deviceType'] = 'TABLET';
                } else {
                    $device_details['deviceType'] = 'TV';
                }

                break;
            }
        }

        foreach ($SC_DEVICES as $d) {
            if (strstr($user_agent, $d) !== false) {
                $device_details['deviceOS'] = $d;
                break;
            }
        }

        foreach ($SC_BROWSERS as $b) {
            if (strstr($user_agent, $b) !== false) {
                $device_details['browser'] = $b;
                break;
            }
        }

        return $device_details;
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
     * Return hash configuration value.
     *
     * @return string
     */
    public function getHash()
    {
        return $this->getConfigValue('hash');
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
     * Return bool|int value depends of the selected option
     * is enabled or not.
     *
	 * @param bool $return_value - by default is false, set true to get int value
     * @return bool|int
     */
    public function isDebugEnabled($return_value = false)
    {
		if($return_value) {
			return (int)$this->getConfigValue('debug');
		}
		
        return (bool)$this->getConfigValue('debug');
    }
	
	public function useUPOs()
	{
		return (bool)$this->getConfigValue('use_upos');
	}

    public function getSourcePlatformField()
    {
        return "Magento Plugin {$this->moduleList->getOne(self::MODULE_NAME)['setup_version']}";
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
        $quoteId	= $this->checkoutSession->getQuoteId();
        $version	= $this->productMetadata->getVersion(); //will return the magento version
		
        if ($this->versionNum != 0 && $this->versionNum < 220) {
            return $this->urlBuilder->getUrl(
                'safecharge/payment/callback_completeold',
                ['quote' => $quoteId]
            );
        }
        
        return $this->urlBuilder->getUrl(
            'safecharge/payment/callback_complete',
            ['quote' => $quoteId]
        )
            . '?form_key=' . $this->formKey->getFormKey();
    }

    /**
     * @return string
     */
    public function getCallbackPendingUrl()
    {
        $quoteId = $this->checkoutSession->getQuoteId();
        
        if ($this->versionNum != 0 && $this->versionNum < 220) {
            return $this->urlBuilder->getUrl(
                'safecharge/payment/callback_completeold',
                ['quote' => $quoteId]
            );
        }
        
        return $this->urlBuilder->getUrl(
            'safecharge/payment/callback_complete',
            ['quote' => $quoteId]
        )
            . '?form_key=' . $this->formKey->getFormKey();
    }

    /**
     * @return string
     */
    public function getCallbackErrorUrl()
    {
        $quoteId = $this->checkoutSession->getQuoteId();

        if ($this->versionNum != 0 && $this->versionNum < 220) {
                return $this->urlBuilder->getUrl(
                    'safecharge/payment/callback_errorold',
                    ['quote' => $quoteId]
                );
        }
        
        return $this->urlBuilder->getUrl(
            'safecharge/payment/callback_error',
            ['quote' => $quoteId]
        )
           . '?form_key=' . $this->formKey->getFormKey();
    }

    /**
     * @return string
     */
    public function getCallbackDmnUrl($incrementId = null, $storeId = null)
    {
        $quoteId    = $this->checkoutSession->getQuoteId();
        $url        =  $this->getStoreManager()
            ->getStore((is_null($incrementId)) ? $this->storeId : $storeId)
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        
        if ($this->versionNum != 0 && $this->versionNum < 220) {
            return $url
                . 'safecharge/payment/callback_dmnold/order/'
                . (is_null($incrementId) ? $this->getReservedOrderId() : $incrementId)
                . '?quote=' . $quoteId;
        }
        
        return $url
            . 'safecharge/payment/callback_dmn/order/'
            . (is_null($incrementId) ? $this->getReservedOrderId() : $incrementId)
            . '?form_key=' . $this->formKey->getFormKey()
            . '&quote=' . $quoteId;
    }

    /**
     * @return string
     */
    public function getBackUrl()
    {
        return $this->urlBuilder->getUrl('checkout/cart');
    }
    
    public function getPaymentAction()
    {
        return $this->getConfigValue('payment_action');
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
	
	public function getNuveiUseCcOnly()
	{
		return $this->checkoutSession->getNuveiUseCcOnly();
	}
	
	public function setNuveiUseCcOnly($val)
	{
		$this->checkoutSession->setNuveiUseCcOnly($val);
	}
	
	public function setQuotePaymentMethod($method)
	{
		$this->checkoutSession->getQuote()->getPayment()->setMethod($method);
		$this->createLog($this->checkoutSession->getQuote()->getPayment()->getMethod(), 'quote payment method');
	}
}
