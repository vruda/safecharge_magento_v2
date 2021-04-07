<?php

namespace Nuvei\Payments\Controller\Frontend\Order;

//use Magento\Sales\Controller\OrderInterface;
//use Magento\Framework\App\Action\HttpGetActionInterface;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Nuvei\Payments\Model\Config;

//class SubscriptionsHistory extends \Magento\Framework\App\Action\Action implements OrderInterface, HttpGetActionInterface
class SubscriptionsHistory extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\CsrfAwareActionInterface
{
    protected $resultPageFactory;
    
    private $httpRequest;
    private $jsonResultFactory;
    private $productRepository;
    private $request;
    private $configurable;
    private $helper;
    private $config;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\Request\Http $httpRequest,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurable,
        \Magento\Framework\Pricing\Helper\Data $helper,
        Config $config
    ) {
        $this->resultPageFactory    = $resultPageFactory;
        $this->httpRequest          = $httpRequest;
        $this->jsonResultFactory    = $jsonResultFactory;
        $this->productRepository    = $productRepository;
        $this->request              = $request;
        $this->configurable         = $configurable;
        $this->helper               = $helper;
        $this->config               = $config;
        
        parent::__construct($context);
    }
    
    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    
    /**
     * Customer order subscriptions history
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        if($this->httpRequest->isAjax()) {
            $data = $this->getProductDetails();
            
            $jsonOutput = $this->jsonResultFactory->create();
            
            $jsonOutput->setHttpResponseCode(200);
            $jsonOutput->setData(json_encode($data));
            
            return $jsonOutput;
        }
        
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('My Nuvei Subscriptions'));

//        $block = $resultPage->getLayout()->getBlock('customer.account.link.back');
//        if ($block) {
//            $block->setRefererUrl($this->_redirect->getRefererUrl());
//        }
        return $resultPage;
    }
    
    private function getProductDetails()
    {
        try {
            $params = $this->request->getParams();

            $this->config->createLog($params, 'SubscriptionsHistory $params');

            if(empty($params) 
                || empty($params['prodId']) 
                || !is_numeric($params['prodId'])
                || empty($params['prodOptions'])
                || !is_array($params['prodOptions'])
            ) {
                return [];
            }

            $product_id = (int) $params['prodId'];
            $product    = $this->productRepository->getById($product_id);
            $usedChild  = $this->configurable->getProductByAttributes($params['prodOptions'], $product);
            $units      = [
                'day'       => __('day'),
                'days'      => __('days'),
                'month'     => __('month'),
                'months'    => __('months'),
                'year'      => __('year'),
                'years'     => __('years'),
            ];
            
            // 
            $rec_len    = '';
            $period     = $usedChild->getCustomAttribute(Config::PAYMENT_SUBS_END_AFTER_PERIOD);
            $unit       = $usedChild->getCustomAttribute(Config::PAYMENT_SUBS_END_AFTER_UNITS);
            
            if(!empty($period)) {
                $period = $period->getValue();
            }
            if(!empty($unit)) {
                $unit = $unit->getValue();
            }
            
            if(is_numeric($period)) {
                $rec_len    = $period . ' ';
                
                if($period > 1) {
                    $rec_len .= $units[$unit . 's'];
                }
                else {
                    $rec_len .= $units[$unit];
                }
            }
            //
            
            //
            $rec_period = '';
            $period     = $usedChild->getCustomAttribute(Config::PAYMENT_SUBS_RECURR_PERIOD);
            $unit       = $usedChild->getCustomAttribute(Config::PAYMENT_SUBS_RECURR_UNITS);
            
            if(!empty($period)) {
                $period = $period->getValue();
            }
            if(!empty($unit)) {
                $unit = $unit->getValue();
            }
            
            if(is_numeric($period)) {
                $rec_period = __('Every') . ' ' . $period . ' ';
                
                if($period > 1) {
                    $rec_period .= $units[$unit . 's'];
                }
                else {
                    $rec_period .= $units[$unit];
                }
            }
            //
            
            //
            $trial_period   = __('None');
            $period         = $period = $usedChild->getCustomAttribute(Config::PAYMENT_SUBS_TRIAL_PERIOD);
            $unit           = $usedChild->getCustomAttribute(Config::PAYMENT_SUBS_TRIAL_UNITS);
            
            if(!empty($period)) {
                $period = $period->getValue();
            }
            if(!empty($unit)) {
                $unit = $unit->getValue();
            }
            
            if(is_numeric($period) && $period > 0) {
                $trial_period = $period . ' ';
                
                if($period > 1) {
                    $trial_period .= $units[$unit . 's'];
                }
                else {
                    $trial_period .= $units[$unit];
                }
            }
            //
            
            return [
                'rec_len'       => $rec_len,
                'rec_period'    => $rec_period,
                'trial_period'  => $trial_period,
                'rec_amount'    => $this->helper->currency(
                    $usedChild->getCustomAttribute(Config::PAYMENT_SUBS_REC_AMOUNT)->getValue(), 
                    true, 
                    false
                ),
            ];
        }
        catch(Exception $e) {
            $this->config->createLog($e->getMessage(), 'SubscriptionsHistory getProductDetails() Exception:');
            return [];
        }
    }
}
