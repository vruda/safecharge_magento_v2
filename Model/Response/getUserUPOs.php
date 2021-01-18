<?php

namespace Nuvei\Payments\Model\Response;

use Nuvei\Payments\Lib\Http\Client\Curl;
use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\Config;
use Nuvei\Payments\Model\Logger as SafechargeLogger;
use Nuvei\Payments\Model\ResponseInterface;

/**
 * Nuvei Payments get merchant payment methods response model.
 */
class getUserUPOs extends AbstractResponse implements ResponseInterface
{
    /**
     * @var array
     */
    protected $scPaymentMethods = [];
    

    /**
     * AbstractResponse constructor.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config $config
     * @param int $requestId
     * @param Curl $curl
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        $requestId,
        Curl $curl
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $requestId,
            $curl
        );
    }

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        
        foreach ((array) $body['paymentMethods'] as $k => $method) {
			if(!empty($method['expiryDate']) && date('Ymd') > $method['expiryDate']) {
				continue;
			}

			if(empty($method['upoStatus']) || $method['upoStatus'] !== 'enabled') {
				continue;
			}
			
            $pm = $method;
            
			$this->scPaymentMethods[] = $pm;
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
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'paymentMethods'
        ];
    }
}
