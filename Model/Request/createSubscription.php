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
 * Safecharge Safecharge get user payment options request model.
 */
class createSubscription extends AbstractRequest implements RequestInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;
	
	protected $plan_id;
	protected $upo_id;


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
	
	// by default 1 -> no plan
	public function setPlanId($plan_id = 1) {
		$this->plan_id = $plan_id;
	}
	
	public function setUpoId($upo_id = 0) {
		$this->upo_id = $upo_id;
	}

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::CREATE_SUBSCRIPTION;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::CREATE_SUBSCRIPTION_HANDLER;
    }

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
        $this->sendRequest();

        return $this->getResponseHandler()->process();
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getParams()
    {
        $params = [
            'planId'				=> $this->plan_id,
            'userPaymentOptionId'	=> $this->upo_id,
            'initialAmount'			=> '0.00',
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
            'userTokenId',
            'planId',
            'userPaymentOptionId',
            'initialAmount',
            'timeStamp',
        ];
    }
}
