<?php

namespace Safecharge\Safecharge\Controller\Payment;

use Magento\Framework\App\Action\Action;

/**
 * Safecharge Safecharge payment authenticate controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Authenticate extends Action
{
    public function execute()
    {
        /** @var \Magento\Framework\View\Layout $layout */
        $layout = $this->_view->getLayout();

        $block = $layout->createBlock(\Safecharge\Safecharge\Block\Payment\Authenticate\Form::class);
        $block->setTemplate('Safecharge_Safecharge::payment/authenticate/form.phtml');

        $this->getResponse()->setBody($block->toHtml());
    }
}
