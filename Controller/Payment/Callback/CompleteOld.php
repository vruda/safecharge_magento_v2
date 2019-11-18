<?php

namespace Safecharge\Safecharge\Controller\Payment\Callback;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\OrderFactory;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;

/**
 * Safecharge Safecharge redirect success controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
//class Success extends Action
class CompleteOld extends Action implements CsrfAwareActionInterface
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var AuthorizeCommand
     */
    private $authorizeCommand;

    /**
     * @var CaptureCommand
     */
    private $captureCommand;

    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var PaymentRequestFactory
     */
    private $paymentRequestFactory;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Onepage
     */
    private $onepageCheckout;

    /**
     * Object constructor.
     *
     * @param Context                 $context
     * @param PaymentRequestFactory   $paymentRequestFactory
     * @param OrderFactory            $orderFactory
     * @param ModuleConfig            $moduleConfig
     * @param AuthorizeCommand        $authorizeCommand
     * @param CaptureCommand          $captureCommand
     * @param SafechargeLogger        $safechargeLogger
     * @param DataObjectFactory       $dataObjectFactory
     * @param CartManagementInterface $cartManagement
     * @param CheckoutSession         $checkoutSession
     * @param Onepage                 $onepageCheckout
     */
    public function __construct(
        Context $context,
        PaymentRequestFactory $paymentRequestFactory,
        OrderFactory $orderFactory,
        ModuleConfig $moduleConfig,
        AuthorizeCommand $authorizeCommand,
        CaptureCommand $captureCommand,
        SafechargeLogger $safechargeLogger,
        DataObjectFactory $dataObjectFactory,
        CartManagementInterface $cartManagement,
        CheckoutSession $checkoutSession,
        Onepage $onepageCheckout
    ) {
        parent::__construct($context);

        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->authorizeCommand = $authorizeCommand;
        $this->captureCommand = $captureCommand;
        $this->safechargeLogger = $safechargeLogger;
        $this->paymentRequestFactory = $paymentRequestFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cartManagement = $cartManagement;
        $this->checkoutSession = $checkoutSession;
        $this->onepageCheckout = $onepageCheckout;
    }
	
    /**
     * @return ResultInterface
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function execute()
    {
		$params = $this->getRequest()->getParams();
		$this->moduleConfig->createLog($params, 'Success params:');

        try {
            $result = $this->placeOrder();
			
            if ($result->getSuccess() !== true) {
				$this->moduleConfig->createLog($result->getErrorMessage(), 'Success Callback error - place order error');
				
                throw new PaymentException(__($result->getErrorMessage()));
            }
        }
        catch (PaymentException $e) {
			$this->moduleConfig->createLog($e->getMessage(), 'Complete Callback Process Error:');
            $this->messageManager->addErrorMessage($e->getMessage());
			
			return $resultRedirect;
        }

		$resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
		$resultRedirect->setUrl(
			$this->_url->getUrl('checkout/onepage/success/')
			. (!empty($_GET['form_key']) ? '?form_key=' . $_GET['form_key'] : '')
		);
		
        return $resultRedirect;
    }

    /**
     * Place order.
     *
     * @return DataObject
     */
    private function placeOrder()
    {
        $result = $this->dataObjectFactory->create();

        try {
            /**
             * Current workaround depends on Onepage checkout model defect
             * Method Onepage::getCheckoutMethod performs setCheckoutMethod
             */
            $this->onepageCheckout->getCheckoutMethod();

            $orderId = $this->cartManagement->placeOrder($this->getQuoteId());

            $result
                ->setData('success', true)
                ->setData('order_id', $orderId);

            $this->_eventManager->dispatch(
                'safecharge_place_order',
                [
                    'result' => $result,
                    'action' => $this,
                ]
            );
        }
        catch (\Exception $exception) {
			$this->moduleConfig->createLog($exception->getMessage(), 'Success Callback Response Exception: ');
            
            $result
                ->setData('error', true)
                ->setData(
                    'error_message',
                    __('An error occurred on the server. Please try to place the order again.')
                );
        }

        return $result;
    }

    /**
     * @return int
     * @throws PaymentException
     */
    private function getQuoteId()
    {
        $quoteId = (int)$this->getRequest()->getParam('quote');

        if ((int)$this->checkoutSession->getQuoteId() === $quoteId) {
            return $quoteId;
        }
		
		$this->moduleConfig->createLog('Success error: Session has expired, order has been not placed.');

        throw new PaymentException(
            __('Session has expired, order has been not placed.')
        );
    }
}
