<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Option\ArrayInterface;

/**
 * Safecharge Safecharge payment action source model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
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
			'Sale' => __('Authorize'),
			'Auth' => __('Authorize and Capture')
        ];
    }
}
