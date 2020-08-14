<?php

namespace Safecharge\Safecharge\Model\Request;

use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;
use Safecharge\Safecharge\Model\RequestInterface;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;

/**
 * Safecharge Safecharge get merchant payment methods request model.
 */
class GetMerchantPaymentMethods extends AbstractRequest implements RequestInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var string
     */
    protected $countryCode;
    
    protected $billingAddress;
    protected $cart;
    protected $store;
    
    /**
     * OpenOrder constructor.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     * @param Factory          $requestFactory
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Store\Api\Data\StoreInterface $store
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory    = $requestFactory;
        $this->cart                = $cart;
        $this->store            = $store;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::GET_MERCHANT_PAYMENT_METHODS_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::GET_MERCHANT_PAYMENT_METHODS_HANDLER;
    }

    /**
     * @param  string|null $countryCode
     * @return $this
     */
    public function setCountryCode($countryCode = null)
    {
        $this->countryCode = (string)$countryCode;
        return $this;
    }
    
    /**
     * @param  array|null $setBillingAddress
     * @return $this
     */
    public function setBillingAddress($setBillingAddress = [])
    {
        $this->billingAddress = $setBillingAddress;
        return $this;
    }
    
    /**
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }
    
    /**
     * @return array
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }
    
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
        $this->sendRequest();

        return $this
            ->getResponseHandler()
            ->process($this->getCountryCode());
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getParams()
    {
        $country_code   = $this->getCountryCode() ?: $this->config->getQuoteCountryCode();
        $tokenRequest   = $this->requestFactory->create(AbstractRequest::OPEN_ORDER_METHOD);
        
        // pass new billing data
        $tokenRequest->setBillingAddress($this->getBillingAddress());
        $tokenRequest->setCountryCode($this->getCountryCode());
        
        $tokenResponse    = $tokenRequest->process();
        
        if (empty($country_code)) {
            try {
                
                $billingAddress = $this->cart->getQuote()->getBillingAddress()->getData();
                
                if (!empty($billingAddress)) {
                    $country_code = $country_code;
                }
            } catch (Exception $e) {
                $this->config->createLog(
                    $e->getMessage(),
                    __FILE__ . ' ' . __FUNCTION__ . '() Exception:'
                );
            }
        }
        
        $languageCode = 'en';
        if ($this->store && $this->store->getLocaleCode()) {
            $languageCode = $this->store->getLocaleCode();
        }
        
        $currencyCode = $this->config->getQuoteBaseCurrency();
        if ((empty($currencyCode) || is_null($currencyCode))
            && $this->cart
        ) {
            $currencyCode = empty($this->cart->getQuote()->getOrderCurrencyCode())
                ? $this->cart->getQuote()->getStoreCurrencyCode() : $this->cart->getQuote()->getOrderCurrencyCode();
        }
		
        $params = [
            'sessionToken'    => $tokenResponse->getSessionToken(),
            "currencyCode"    => $currencyCode,
            "countryCode"    => $country_code,
            "languageCode"    => $languageCode,
        ];

        $params = array_merge_recursive(parent::getParams(), $params);
        
        return $params;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'clientRequestId',
            'timeStamp',
        ];
    }
}
