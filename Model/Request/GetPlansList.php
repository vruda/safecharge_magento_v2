<?php

namespace Nuvei\Payments\Model\Request;

use Nuvei\Payments\Model\AbstractRequest;
use Nuvei\Payments\Model\RequestInterface;
use Nuvei\Payments\Model\Config;
use Nuvei\Payments\Lib\Http\Client\Curl;

class GetPlansList extends AbstractRequest implements RequestInterface
{
    protected $requestFactory;
    
    public function __construct(
        \Nuvei\Payments\Model\Logger $logger,
        Config $config,
        Curl $curl,
        \Nuvei\Payments\Model\Response\Factory $responseFactory,
        \Nuvei\Payments\Model\Request\Factory $requestFactory
    ) {
        parent::__construct(
            $logger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory = $requestFactory;
    }
    
    public function process()
    {
        $this->sendRequest();

        return $this
            ->getResponseHandler()
            ->process();
    }
    
    protected function getRequestMethod()
    {
        return self::GET_MERCHANT_PAYMENT_PLANS_METHOD;
    }
    
    protected function getResponseHandlerType()
    {
        return \Nuvei\Payments\Model\AbstractResponse::GET_MERCHANT_PAYMENT_PLANS_HANDLER;
    }
    
    protected function getParams()
    {
        $params = array_merge_recursive(
            [
				'planStatus'	=> 'ACTIVE',
				'currency'		=> '',
			],
            parent::getParams()
        );
        
        return $params;
    }
    
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'currency',
            'planStatus',
            'timeStamp',
        ];
    }
}
