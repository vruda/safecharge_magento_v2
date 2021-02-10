<?php

namespace Nuvei\Payments\Model\Response;

class GetPlansList extends \Nuvei\Payments\Model\AbstractResponse implements \Nuvei\Payments\Model\ResponseInterface
{
    protected $config;
    
    public function __construct(
        \Nuvei\Payments\Model\Logger $logger,
        \Nuvei\Payments\Model\Config $config,
        $requestId,
        \Nuvei\Payments\Lib\Http\Client\Curl $curl
    ) {
        parent::__construct(
            $logger,
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
                || empty($body['total']) || (int) $body['total'] < 1
            ) {
                $this->config->createLog('GetPlansList error - status error or missing plans. '
                    . 'Check the response above!');
                return $this;
            }

            $this->config->createLog('response process');
            
            file_put_contents(
                $tempPath. DIRECTORY_SEPARATOR . \Nuvei\Payments\Model\Config::PAYMENT_PLANS_FILE_NAME,
                json_encode($body)
            );
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
