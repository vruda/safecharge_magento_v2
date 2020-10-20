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
class CreateSubscription extends AbstractRequest implements RequestInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;
	
//	protected $plan_id;
//	protected $upo_id;
//	protected $user_token_id;
	protected $order_id;
	protected $subscr_data;


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
//	public function setPlanId($plan_id = 1) {
//		$this->plan_id = intval($plan_id);
//		return $this;
//	}
//	
//	public function setUpoId($upo_id = 0) {
//		$this->upo_id = intval($upo_id);
//		return $this;
//	}
//	
//	public function setUserTokenId($email) {
//		$this->user_token_id = $email;
//		return $this;
//	}
//	
	public function setOrderId($order_id) {
		$this->order_id = $order_id;
		return $this;
	}
	
	public function setData($subscr_data) {
		$this->subscr_data = $subscr_data;
		return $this;
	}

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::CREATE_SUBSCRIPTION_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
//        return AbstractResponse::CREATE_SUBSCRIPTION_HANDLER;
        return;
    }

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
        return $this->sendRequest(true);
//        $req_resp = $this->sendRequest(true);
//		return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getParams()
    {
//        $params = [
//            'planId'				=> $this->plan_id,
//            'userPaymentOptionId'	=> $this->upo_id,
//            'initialAmount'			=> '0.00',
//			'userTokenId'			=> $this->user_token_id,
//        ];

        $params = array_merge_recursive(parent::getParams(), $this->subscr_data);
		$params['clientRequestId'] = $this->order_id; // override magento's number
        
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
            'recurringAmount',
            'currency',
            'timeStamp',
        ];
    }
}
