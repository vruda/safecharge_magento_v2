<?php

namespace Safecharge\Safecharge\Model\Response;

use Magento\Framework\Locale\Resolver;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge get merchant payment methods response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class GetMerchantPaymentMethods extends AbstractResponse implements ResponseInterface
{

    /**
     * @var Resolver
     */
    protected $localeResolver;

    /**
     * @var array
     */
    protected $scPaymentMethods = [];
    
    /**
     *
     * @var string - the session token returned from APMs
     */
    protected $sessionToken = '';

    /**
     * AbstractResponse constructor.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config $config
     * @param int $requestId
     * @param Curl $curl
     * @param Resolver $localeResolver
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        $requestId,
        Curl $curl,
        Resolver $localeResolver
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $requestId,
            $curl
        );

        $this->localeResolver = $localeResolver;
    }

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process($countryCode = null)
    {
        parent::process();

        $body					= $this->getBody();
//        $this->scPaymentMethods	= (array) $body['paymentMethods'];
        $this->sessionToken     = (string) $body['sessionToken'];
        $langCode				= $this->getStoreLocale(true);
        $countryCode			= $countryCode ?: $this->config->getQuoteCountryCode();
		
		############## new code
		
		foreach ((array) $body['paymentMethods'] as $k => $method) {
			if (!$countryCode && isset($method["paymentMethod"]) && $method["paymentMethod"] !== 'cc_card') {
                continue;
            }
			
			$default_dnames = array();
			$locale_dnames	= array();
			$pm				= $method;
			
			if (isset($method["paymentMethodDisplayName"]) && is_array($method["paymentMethodDisplayName"])) {
				foreach ($method["paymentMethodDisplayName"] as $kk => $dname) {
					 if ($dname["language"] === $langCode) {
						$locale_dnames = $dname;
						break;
                    }
					// default language
					elseif($dname["language"] == 'en') {
						$default_dnames = $dname;
					}
				}
			}
			
			if (!empty($method["logoURL"])) {
                $pm["logoURL"] = preg_replace('/\.svg\.svg$/', '.svg', $method["logoURL"]);
            }
			
			if(!empty($locale_dnames)) {
				$pm["paymentMethodDisplayName"]	= $locale_dnames;
				$this->scPaymentMethods[]		= $pm;
			}
			elseif(!empty($default_dnames)) {
				$pm["paymentMethodDisplayName"] = $default_dnames;
				$this->scPaymentMethods[]		= $pm;
			}
		}
		
		$this->config->createLog($body['paymentMethods'], 'process() paymentMethods 2:');
		
		return $this;
		
		############# old code
		
//		$this->config->createLog($body['paymentMethods'], 'process() paymentMethods:');
        /*
		foreach ($this->scPaymentMethods as $k => &$method) {
            if (!$countryCode && isset($method["paymentMethod"]) && $method["paymentMethod"] !== 'cc_card') {
                unset($this->scPaymentMethods[$k]);
                continue;
            }
            
			if (isset($method["paymentMethodDisplayName"]) && is_array($method["paymentMethodDisplayName"])) {
//				$default_dnames = array();
//				$locale_dnames	= array();
				
                foreach ($method["paymentMethodDisplayName"] as $kk => $dname) {
                    if ($dname["language"] === $langCode) {
                        $method["paymentMethodDisplayName"] = $dname;
                        break;
						
//						$locale_dnames = $dname;
//						break;
                    }
					// default language
					elseif($dname["language"] == 'en') {
						$default_dnames = $dname;
					}
                }
				
                if (!isset($method["paymentMethodDisplayName"]["language"])) {
//                if (empty($locale_dnames) and empty($default_dnames)) {
                    unset($this->scPaymentMethods[$k]);
                }
				
//				if(!empty($locale_dnames)) {
//					$method["paymentMethodDisplayName"] = $locale_dnames;
//				}
//				elseif(!empty($default_dnames)) {
//					$method["paymentMethodDisplayName"] = $default_dnames;
//				}
            }
			
            if (isset($method["logoURL"]) && $method["logoURL"]) {
                $method["logoURL"] = preg_replace('/\.svg\.svg$/', '.svg', $method["logoURL"]);
            }
        }
		
		$this->config->createLog($body['paymentMethods'], 'process() paymentMethods 2:');
		
        $this->scPaymentMethods = array_values($this->scPaymentMethods);
		
        return $this;
		 */
    }

    /**
     * @return string
     */
    public function getScPaymentMethods()
    {
        return $this->scPaymentMethods;
    }
    
    /**
     * Get the session token from the APMs
     * 
     * @return string
     */
    public function getSessionToken()
    {
        return $this->sessionToken;
    }

    /**
     * Return store locale.
     *
     * @return string
     */
    protected function getStoreLocale($twoLetters = true)
    {
        $locale = $this->localeResolver->getLocale();
        return ($twoLetters) ? substr($locale, 0, 2) : $locale;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'paymentMethods'
        ];
    }
}
