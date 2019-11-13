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
    protected $paymentMethods = [];

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

        $body = $this->getBody();

        $this->paymentMethods = (array) $body['paymentMethods'];

        $langCode = $this->getStoreLocale(true);
        $countryCode = $countryCode ?: $this->config->getQuoteCountryCode();
        foreach ($this->paymentMethods as $k => &$method) {
            if ((!$countryCode || $this->config->getPaymentAction() !== Payment::ACTION_AUTHORIZE_CAPTURE) && isset($method["paymentMethod"]) && $method["paymentMethod"] !== 'cc_card') {
                unset($this->paymentMethods[$k]);
                continue;
            }
            if (isset($method["paymentMethodDisplayName"]) && is_array($method["paymentMethodDisplayName"])) {
                foreach ($method["paymentMethodDisplayName"] as $kk => $dname) {
                    if ($dname["language"] === $langCode) {
                        $method["paymentMethodDisplayName"] = $dname;
                        break;
                    }
                }
                if (!isset($method["paymentMethodDisplayName"]["language"])) {
                    unset($this->paymentMethods[$k]);
                }
            }
            if (isset($method["logoURL"]) && $method["logoURL"]) {
                $method["logoURL"] = preg_replace('/\.svg\.svg$/', '.svg', $method["logoURL"]);
            }
        }
        $this->paymentMethods = array_values($this->paymentMethods);

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentMethods()
    {
        return $this->paymentMethods;
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
            'paymentMethods',
        ];
    }
}
