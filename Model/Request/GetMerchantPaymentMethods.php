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
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
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
        RequestFactory $requestFactory
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory = $requestFactory;
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
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
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
        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::OPEN_ORDER_METHOD);
        
		$objectManager	= \Magento\Framework\App\ObjectManager::getInstance();
		$cart			= $objectManager->get('\Magento\Checkout\Model\Cart');
		$store			= $objectManager->get('Magento\Store\Api\Data\StoreInterface');
		
		$tokenResponse	= $tokenRequest->process();
		$country_code	= $this->getCountryCode() ?: $this->config->getQuoteCountryCode();
		
		if (empty($country_code)) {
			try {
				
				$billingAddress = $cart->getQuote()->getBillingAddress()->getData();
				
				if (!empty($billingAddress)) {
					$country_code = $country_code;
				}
			}
			catch (Exception $e) {
				$this->config->createLog(
					$e->getMessage(), 
					__FILE__ . ' ' . __FUNCTION__ . '() Exception:'
				);
			}
		}
		
		$languageCode = 'en';
			
		if ($store && $store->getLocaleCode()) {
			$languageCode = $store->getLocaleCode();
		}
		
		$currencyCode = $this->config->getQuoteBaseCurrency();
		
		if (
			(empty($currencyCode) || is_null($currencyCode))
			&& $cart
		) {
			$currencyCode = empty($cart->getQuote()->getOrderCurrencyCode())
				? $cart->getQuote()->getStoreCurrencyCode() : $cart->getQuote()->getOrderCurrencyCode();
		}
		
        $params = [
            'sessionToken'	=> $tokenResponse->getSessionToken(),
            "currencyCode"	=> $currencyCode,
            "countryCode"	=> $country_code,
            "languageCode"	=> $languageCode,
        ];

        $params = array_merge_recursive(parent::getParams(), $params);
		
		$this->config->createLog('Get the APMs.');

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
