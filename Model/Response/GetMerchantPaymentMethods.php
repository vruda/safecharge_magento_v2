<?php

namespace Nuvei\Payments\Model\Response;

use Magento\Framework\Locale\Resolver;
use Nuvei\Payments\Lib\Http\Client\Curl;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Config;
use Nuvei\Payments\Model\Logger as SafechargeLogger;
use Nuvei\Payments\Model\ResponseInterface;

/**
 * Nuvei Payments get merchant payment methods response model.
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

        $body                = $this->getBody();
        $this->sessionToken    = (string) $body['sessionToken'];
        $langCode            = $this->getStoreLocale(true);
        $countryCode        = $countryCode ?: $this->config->getQuoteCountryCode();
        
        foreach ((array) $body['paymentMethods'] as $k => $method) {
            if (!$countryCode && isset($method["paymentMethod"]) && $method["paymentMethod"] !== 'cc_card') {
                continue;
            }
			
			// when we have product with a Payment plan, skip all APMs
			/*
			if(
				$this->config->getNuveiUseCcOnly()
				&& !empty($method["paymentMethod"])
				&& $method["paymentMethod"] !== 'cc_card'
			) {
				continue;
			}
			 */
            
            $default_dnames	= [];
            $locale_dnames  = [];
            $pm             = $method;
            
            if (isset($method["paymentMethodDisplayName"]) && is_array($method["paymentMethodDisplayName"])) {
                foreach ($method["paymentMethodDisplayName"] as $kk => $dname) {
                    if ($dname["language"] === $langCode) {
                        $locale_dnames = $dname;
                        break;
                    } elseif ($dname["language"] == 'en') { // default language
                        $default_dnames = $dname;
                    }
                }
            }
            
            // set custom payment logo for CC
            if ('cc_card' == $method["paymentMethod"]) {
                $pm["logoURL"] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHg'
                    . 'AAAAZCAYAAAD6zOotAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt'
                    . '4AAAAB3RJTUUH5AIDDBYpfkTbZQAACYtJREFUaN7tmnt0FPUVxz8zs7OPPDZ'
                    . 'EzG4CCSFCCM/wihA0gAKKSBQVAvjAgPim+kdrrfX09BxrS6FarfRQAY2E1qJHpJ'
                    . '7agg8UUEHyoBDkYSghCUsg7G4wsMtmd3Z2Z/pHICGFXSysJ1vbe86c3fnNnZnv7'
                    . '37n3vv73d9PoFNMwADASPeJBtQB3gjX+wI96V5pAY5EuGYF+gFiN+ILAgfP/iIAW'
                    . 'K3WaRaLZXVBQYFotVqFbkMWDOoVFRWiqqpLXS7XC+cbLi0t7ZOcnJy+/fr1E7qT3'
                    . 'bq6Or2xsbHR7XZPATzn2m0229NGo/HpsWPHakajsdswejwefefOnbrf7y/1eDwfAp'
                    . 'jtdrvT6XTq8SCKouj5+fkngSHnQKenp7+xatUqRY8TWbFiRTAjI+P18+w6dMSIES2'
                    . 'KEh8QT5w4odvtdhdgEoHB48aNE2w2G/EgRqORhQsXpoiiWHSuTdf1G+fNm2ckTqS0'
                    . 'tFTWNG3yuXNZlscvWLCgh9EYHxDtdjuFhYUCMEQEzAkJCSJxJBaLRZJlOfHceTgcN'
                    . 'prN5rjBZzabCYfDHWyKomixWCxSPNkwMTFRAMxxRez/JfZi+L51KLTzKwLLywlu+R'
                    . 'LtSBOCyYiUPwjTrOmYH52HYE1Gc3xEuO5dNGcVejiIIJkQ0wsRr5mBlD3tO8Wnn/b'
                    . 'iX15O8C8fEN5/ED2gIPXNQp5chHlRKYaRQzniC/HSAQ+bmwPUeVUskkB/q8ydWQk8'
                    . 'PCCZnibxf5DgcBjfU8/jX/YGaFqnQZUgoeo9hKr3oLz6Kkm/yCAUOtDV6KqXcOPf2'
                    . '4/eNyAXvYSQEPsxibp5O967F6G5WrpCbzxKuOwt/Kvf4cMXVvJQ2kAUvfN6IKxT3a'
                    . 'JQ3aLwytceXruuJ7dlJnyrd0b8FFpbfRxxnOw4FCXU5XrTsVaOOE4SCKgcbz7V8f/'
                    . 'soIiGBjeVVfU4jn7T5b5AQOWI4yQutzemxjvzyDP4f/d6F3LPF8GokTyiFsOWTxA9'
                    . 'wcgT8WNbUTfNA9UXW3I/r+T0tPsuIPd8+bLwDvYdtzD+2DcRdZyBMHdtcfHxcf+Ve'
                    . 'XCbP8jHm/ax/r1/sHdfE5m9U9m29VlkWeJwvYuJk5eSmGCk6sufc8OUpfh8Cju++B'
                    . 'ler58nf7iW2trmjme98doDTL1pKN4zASZMWoLL5aF/PxufffpMTIynrN9IoOyt6IO'
                    . 'O0R6klBBoINe2ooy2gXTx6ar2zX7U6ueRr1sSm7DsD+C9ZxEE1Yg6xzP6s6PwDgBG'
                    . 'tXipT7ZQn2y5eBrSYe7nbhruyiTFKF6eB/fulcpDCyfy9puPIkkiTcdaqT3YTtqb'
                    . 'a3eg6zpzZo/heHMrPp/C1T2TyMpMZeHDq6mtbeaO20exZHEJ990zjsGDegGw7t1qX'
                    . 'K722sDhejcejz8mBvQv/n30Tlp0zLmdHin4Q0gt0d8drluH7nfH5gNcsw7t2ImoOt'
                    . 'XXFqMLnXSMdZ2Oqt8a1Fh1yHv5Ifqc9OiRwLChmQDs238MRQmxbv1ORFFgwf1F7Nrt'
                    . 'AGDkyGxOnWrjaFN7eBmen8W9cwtZuriErMyr0HWd1Wu2IUkiUyYPRtd1du9xXHlts'
                    . '9lFaPe+6HPrPuGzNbvzOn4ycImcrqA1b49NhW7Dp9E9XJQ4fM3ILm1ZbQqJ6FHv23'
                    . 'jMf+UEA1x/XX8A9u5rYsMHe2ht9THpxkHk5KSxa3d7WXbUyGxSUxOZeedoAJ775V'
                    . '+ZPuNl6hvavWDr5wepb3AzoWgAxbcOB2DXriNXTnDjUdCjG0JMvjAvC4HwpUOrpy'
                    . 'E247/Go9Hzc2oqYenfsqUOaUL0ftV5QrEiOLeD4DfX7gDgwQUTANhdc5bgEdkALH'
                    . 'v5Xl5+8W56XpXEV3ubmL+wrD0Pl38BwOySMRSM6ttOcM2VE4xRvjRR4cvsuRSb4o'
                    . 'ogR8coqhfPzZeizyxduuT9raZJYwpykGWJr/Y2EQqFyRuQTtH1uXjPBDhU50QUBYY'
                    . 'PzyIQUDGbZWbPupaM9BTm3rcCd4uXhgY3Wz+rBeCxH/yx47m7axzouo4gXH5tXsrNA'
                    . 'dkAamRzaKcvZFO3XLrrYkq/mBAsDc6NmkYMXg8W/xn8lqROcgUBN9HtkpdiiA3BFou'
                    . 'R0SP7UlF1GIAH5o9HEARqahxoms7AvHSSk8zMmLkMRQmRkZFCTU17fp07ewzlf9qO'
                    . 'punkDUgnN9cOQFV1Ay6Xh8bGFnJy0i7fO6zJGG+eGDXPBR0GdFVAkDtDXtgWfR4pmH'
                    . 'si9p4QE4JNJcUof34vSojRyftnBTXDp3Q0HU6xoOjRCb43Jyk2IRrgtuLh5A/L5NqC'
                    . 'nI4863R6yB+WybRb8tF1nUEDeyGKAvX1bgYOzOC3v5nDT5+ezte1zeQPy+TFpXNYub'
                    . 'yUlctLeWD+ePKHZXK0qfWKDZjw3I/avTiS/ULQtsfa6dGpJrQepuheN/QREGJTXjbe'
                    . 'fjOGwlFRdcZWvo8p2Nb+8QkC22w9ouoPTpG5NdMSu0rW/PuLmH9/UZe2WTMLmDWzoON'
                    . '8ya9mXfTed9Y+dkHbE49P5onHJ8em3jo6n6Rlz3Pm8WcjDrj8B5IwpAUxDlRR81KJFv'
                    . '2knBkYhj4CWigm+BAErG//gVNFd6I1NV9Uxeo9ybQPVvJ+8ZNszLZx0hw5b6eZJf42'
                    . 'yY78LVLb92axwfzoPJLfWYFojxDudVC0iWgzFqFHWpkSZQyjfow84ZV275VMMcMnZm'
                    . 'fSY8f7yFPGR9Tpd7qRG8aYaOtljahzY7qZylszuCbZQIJBiJ0H/zeIadZ0jFMnory7'
                    . 'AXXzdrQTbgSDhDR4AKaS4o4wKfp+QrhxA7qrCj3oRTBaEe1jEbNvQUjs9Z3hEzMzSN'
                    . 'n0Fuq2KoLrNxKurUMPhRF72TFOuh5jSTFTEywc1HXec7Sx5USAQ54QJgnyrDIzshIYb'
                    . 'zfznwxJv3erSUJyEuYFczAvmBNZJzEDw5AHYciD3YJRLhqDXDQmMimCQEl2IiXZiVf+'
                    . 'UQEBn8+nxxNJfr8/rKqd1X5JkoKBQCBu8AUCASRJ6lixUFW1ze/3h+PJhmc5DYjAgYq'
                    . 'KCs3pdMYFsGAwSFlZ2SlN07Z1jlGEzeXl5Wq8GG/NmjWqKIod8zJN074oKys7FQwG4w'
                    . 'Kf0+mkoqJCA/YLAImJiVOTkpLWdPeuSkVR9MrKSklV1V+7XK4Xzx9k2my2TX369MnJz'
                    . 'c3t1l2Vhw4d0h0OR4PL5bqJrrsqn5Jl+ZnCwsJu31VZXV2t+Xy+Up/P99H5QIxAHvG'
                    . '9LzobuLqbHcQNRFolSQb6E0f7ov8FuldIY3TuFN0AAAAASUVORK5CYII=';
            } elseif (!empty($method["logoURL"])) {
                $pm["logoURL"] = preg_replace('/\.svg\.svg$/', '.svg', $method["logoURL"]);
            }
			
			// fix for the Neteller field
			if (
				'apmgw_Neteller' == $method["paymentMethod"]
				&& 1 == count($method['fields'])
				&& 'nettelerAccount' == $method['fields'][0]['name']
			) {
				$pm['fields'][0]['name'] = 'Neteller Account';
				$pm['fields'][0]['type'] = 'email';
			}
            
            if (!empty($locale_dnames)) {
                $pm["paymentMethodDisplayName"]	= $locale_dnames;
                $this->scPaymentMethods[]		= $pm;
            } elseif (!empty($default_dnames)) {
                $pm["paymentMethodDisplayName"]	= $default_dnames;
                $this->scPaymentMethods[]       = $pm;
            }
        }
        
        return $this;
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
