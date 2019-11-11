<?php

namespace Safecharge\Safecharge\Block\Payment\Authenticate;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;

/**
 * Safecharge Safecharge payment authenticate form block.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Form extends Template
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * Object constructor.
     *
     * @param Context         $context
     * @param CheckoutSession $checkoutSession
     * @param array           $data
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );

        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return string|null
     */
    public function getAscUrl()
    {
        return $this->checkoutSession->getAscUrl();
    }

    /**
     * @return string|null
     */
    public function getPaReq()
    {
        return $this->checkoutSession->getPaReq();
    }

    /**
     * @return string
     */
    public function getTermUrl()
    {
        $orderId = $this->checkoutSession->getLastRealOrderId();

        return $this->_urlBuilder->getUrl(
            'safecharge/payment/update',
            ['order' => $orderId]
        );
    }

    /**
     * Render block HTML.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ($this->getAscUrl() === null) {
            return '';
        }

        return parent::_toHtml();
    }
}
