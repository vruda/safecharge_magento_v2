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

        $body				= $this->getBody();
        $this->sessionToken	= (string) $body['sessionToken'];
        $langCode			= $this->getStoreLocale(true);
        $countryCode		= $countryCode ?: $this->config->getQuoteCountryCode();
		
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
			
			// set custom payment logo for CC
			if('cc_card' == $method["paymentMethod"]) {
				$pm["logoURL"] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHgAAAAZCAYAAAD6zOotAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH5AIDDBYpfkTbZQAACYtJREFUaN7tmnt0FPUVxz8zs7OPPDZEzG4CCSFCCM/wihA0gAKKSBQVAvjAgPim+kdrrfX09BxrS6FarfRQAY2E1qJHpJ7agg8UUEHyoBDkYSghCUsg7G4wsMtmd3Z2Z/pHICGFXSysJ1vbe86c3fnNnZnv737n3vv73d9PoFNMwADASPeJBtQB3gjX+wI96V5pAY5EuGYF+gFiN+ILAgfP/iIAWK3WaRaLZXVBQYFotVqFbkMWDOoVFRWiqqpLXS7XC+cbLi0t7ZOcnJy+/fr1E7qT3bq6Or2xsbHR7XZPATzn2m0229NGo/HpsWPHakajsdswejwefefOnbrf7y/1eDwfApjtdrvT6XTq8SCKouj5+fkngSHnQKenp7+xatUqRY8TWbFiRTAjI+P18+w6dMSIES2KEh8QT5w4odvtdhdgEoHB48aNE2w2G/EgRqORhQsXpoiiWHSuTdf1G+fNm2ckTqS0tFTWNG3yuXNZlscvWLCgh9EYHxDtdjuFhYUCMEQEzAkJCSJxJBaLRZJlOfHceTgcNprN5rjBZzabCYfDHWyKomixWCxSPNkwMTFRAMxxRez/JfZi+L51KLTzKwLLywlu+RLtSBOCyYiUPwjTrOmYH52HYE1Gc3xEuO5dNGcVejiIIJkQ0wsRr5mBlD3tO8Wnn/biX15O8C8fEN5/ED2gIPXNQp5chHlRKYaRQzniC/HSAQ+bmwPUeVUskkB/q8ydWQk8PCCZnibxf5DgcBjfU8/jX/YGaFqnQZUgoeo9hKr3oLz6Kkm/yCAUOtDV6KqXcOPf24/eNyAXvYSQEPsxibp5O967F6G5WrpCbzxKuOwt/Kvf4cMXVvJQ2kAUvfN6IKxT3aJQ3aLwytceXruuJ7dlJnyrd0b8FFpbfRxxnOw4FCXU5XrTsVaOOE4SCKgcbz7V8f/soIiGBjeVVfU4jn7T5b5AQOWI4yQutzemxjvzyDP4f/d6F3LPF8GokTyiFsOWTxA9wcgT8WNbUTfNA9UXW3I/r+T0tPsuIPd8+bLwDvYdtzD+2DcRdZyBMHdtcfHxcf+VeXCbP8jHm/ax/r1/sHdfE5m9U9m29VlkWeJwvYuJk5eSmGCk6sufc8OUpfh8Cju++Bler58nf7iW2trmjme98doDTL1pKN4zASZMWoLL5aF/PxufffpMTIynrN9IoOyt6IOO0R6klBBoINe2ooy2gXTx6ar2zX7U6ueRr1sSm7DsD+C9ZxEE1Yg6xzP6s6PwDgBGtXipT7ZQn2y5eBrSYe7nbhruyiTFKF6eB/fulcpDCyfy9puPIkkiTcdaqT3YTtqba3eg6zpzZo/heHMrPp/C1T2TyMpMZeHDq6mtbeaO20exZHEJ990zjsGDegGw7t1qXK722sDhejcejz8mBvQv/n30Tlp0zLmdHin4Q0gt0d8drluH7nfH5gNcsw7t2ImoOtXXFqMLnXSMdZ2Oqt8a1Fh1yHv5Ifqc9OiRwLChmQDs238MRQmxbv1ORFFgwf1F7NrtAGDkyGxOnWrjaFN7eBmen8W9cwtZuriErMyr0HWd1Wu2IUkiUyYPRtd1du9xXHlts9lFaPe+6HPrPuGzNbvzOn4ycImcrqA1b49NhW7Dp9E9XJQ4fM3ILm1ZbQqJ6FHv23jMf+UEA1x/XX8A9u5rYsMHe2ht9THpxkHk5KSxa3d7WXbUyGxSUxOZeedoAJ775V+ZPuNl6hvavWDr5wepb3AzoWgAxbcOB2DXriNXTnDjUdCjG0JMvjAvC4HwpUOrpyE247/Go9Hzc2oqYenfsqUOaUL0ftV5QrEiOLeD4DfX7gDgwQUTANhdc5bgEdkALHv5Xl5+8W56XpXEV3ubmL+wrD0Pl38BwOySMRSM6ttOcM2VE4xRvjRR4cvsuRSb4oogR8coqhfPzZeizyxduuT9raZJYwpykGWJr/Y2EQqFyRuQTtH1uXjPBDhU50QUBYYPzyIQUDGbZWbPupaM9BTm3rcCd4uXhgY3Wz+rBeCxH/yx47m7axzouo4gXH5tXsrNAdkAamRzaKcvZFO3XLrrYkq/mBAsDc6NmkYMXg8W/xn8lqROcgUBN9HtkpdiiA3BFouR0SP7UlF1GIAH5o9HEARqahxoms7AvHSSk8zMmLkMRQmRkZFCTU17fp07ewzlf9qOpunkDUgnN9cOQFV1Ay6Xh8bGFnJy0i7fO6zJGG+eGDXPBR0GdFVAkDtDXtgWfR4pmHsi9p4QE4JNJcUof34vSojRyftnBTXDp3Q0HU6xoOjRCb43Jyk2IRrgtuLh5A/L5NqCnI4863R6yB+WybRb8tF1nUEDeyGKAvX1bgYOzOC3v5nDT5+ezte1zeQPy+TFpXNYubyUlctLeWD+ePKHZXK0qfWKDZjw3I/avTiS/ULQtsfa6dGpJrQepuheN/QREGJTXjbefjOGwlFRdcZWvo8p2Nb+8QkC22w9ouoPTpG5NdMSu0rW/PuLmH9/UZe2WTMLmDWzoON8ya9mXfTed9Y+dkHbE49P5onHJ8em3jo6n6Rlz3Pm8WcjDrj8B5IwpAUxDlRR81KJFv2knBkYhj4CWigm+BAErG//gVNFd6I1NV9Uxeo9ybQPVvJ+8ZNszLZx0hw5b6eZJf42yY78LVLb92axwfzoPJLfWYFojxDudVC0iWgzFqFHWpkSZQyjfow84ZV275VMMcMnZmfSY8f7yFPGR9Tpd7qRG8aYaOtljahzY7qZylszuCbZQIJBiJ0H/zeIadZ0jFMnory7AXXzdrQTbgSDhDR4AKaS4o4wKfp+QrhxA7qrCj3oRTBaEe1jEbNvQUjs9Z3hEzMzSNn0Fuq2KoLrNxKurUMPhRF72TFOuh5jSTFTEywc1HXec7Sx5USAQ54QJgnyrDIzshIYbzfznwxJv3erSUJyEuYFczAvmBNZJzEDw5AHYciD3YJRLhqDXDQmMimCQEl2IiXZiVf+UQEBn8+nxxNJfr8/rKqd1X5JkoKBQCBu8AUCASRJ6lixUFW1ze/3h+PJhmc5DYjAgYqKCs3pdMYFsGAwSFlZ2SlN07Z1jlGEzeXl5Wq8GG/NmjWqKIod8zJN074oKys7FQwG4wKf0+mkoqJCA/YLAImJiVOTkpLWdPeuSkVR9MrKSklV1V+7XK4Xzx9k2my2TX369MnJzc3t1l2Vhw4d0h0OR4PL5bqJrrsqn5Jl+ZnCwsJu31VZXV2t+Xy+Up/P99H5QIxAHvG9LzobuLqbHcQNRFolSQb6E0f7ov8FuldIY3TuFN0AAAAASUVORK5CYII=';
			}
			elseif (!empty($method["logoURL"])) {
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
