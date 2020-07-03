<?php

namespace Safecharge\Safecharge\Model\Response;

use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Lib\Http\Client\Curl;

/**
 * Safecharge Safecharge open order response model.
 */
class GetPlansList extends \Safecharge\Safecharge\Model\AbstractResponse implements \Safecharge\Safecharge\Model\ResponseInterface
{
    protected $config;
    
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
        
        $this->config = $config;
    }
    
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        // write the subscriptions to a file
        try {
            $body        = $this->getBody();
            $array_keys = $this->getRequiredResponseDataKeys();
            $tempPath    = $this->config->getTempPath();

            if (empty($body['status']) || $body['status'] != 'SUCCESS'
                || empty($body['total']) || intval($body['total']) < 1
            ) {
                $this->config->createLog('GetPlansList error - status error or missing plans. Check the response above!');
                return $this;
            }

            $this->config->createLog('response process');
            
            $fp = fopen($tempPath. DIRECTORY_SEPARATOR . 'sc_subscriptions.json', 'w');
            fwrite($fp, json_encode($body));
            fclose($fp);
        } catch (Exception $e) {
            $this->config->createLog($e->getMessage(), 'GetPlansList Exception');
        }
        
        return $this;
    }

    /**
     * @return array
     */
//    protected function getRequiredResponseDataKeys()
//    {
//        return ['subscriptions'];
//    }
}
