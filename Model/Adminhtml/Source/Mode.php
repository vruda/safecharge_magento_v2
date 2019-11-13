<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;
use Safecharge\Safecharge\Model\Payment;

/**
 * Safecharge Safecharge mode source model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Mode implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $optionArray = [];
        foreach ($this->toArray() as $value => $label) {
            $optionArray[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $optionArray;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            Payment::MODE_LIVE => __('Live'),
            Payment::MODE_SANDBOX => __('Sandbox'),
        ];
    }
}
