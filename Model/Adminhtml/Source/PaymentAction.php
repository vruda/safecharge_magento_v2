<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Option\ArrayInterface;

/**
 * Safecharge Safecharge payment action source model.
 */
class PaymentAction implements ArrayInterface
{
    /**
     * Possible actions on order place.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            'Sale' => __('Authorize and Capture'),
            'Auth' => __('Authorize')
        ];
    }
}
