<?php

namespace Nuvei\Payments\Model\Response;

use Nuvei\Payments\Model\AbstractResponse;
use Nuvei\Payments\Model\ResponseInterface;

/**
 * Nuvei Payments paymentAPM response model.
 */
class PaymentApm extends AbstractResponse implements ResponseInterface
{

    /**
     * @var string
     */
    protected $redirectUrl = "";

    /**
     * @var string
     */
    protected $responseStatus = "";

    /**
     * @return PaymentApm
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        
        $transactionStatus = '';
        if (!empty($body['transactionStatus'])) {
            $transactionStatus = (string) $body['transactionStatus'];
        }
        
        if (!empty($body['redirectURL'])) {
            $this->redirectUrl = (string) $body['redirectURL'];
        } else {
            switch ($transactionStatus) {
                case 'APPROVED':
                    $this->redirectUrl = $this->config->getCallbackSuccessUrl();
                    break;
                
                case 'PENDING':
                    $this->redirectUrl = $this->config->getCallbackPendingUrl();
                    break;
                
                case 'DECLINED':
                case 'ERROR':
                default:
                    $this->redirectUrl = $this->config->getCallbackErrorUrl();
                    break;
            }
        }
        
        $this->responseStatus = (string) $body['status'];
        return $this;
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @return string
     */
    public function getResponseStatus()
    {
        return $this->responseStatus;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
        //    'redirectURL', // auto login APMs need credentials and does not return redirect URL
            'status',
        ];
    }
}
