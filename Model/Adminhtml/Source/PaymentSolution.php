<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Safecharge\Safecharge\Model\Payment;
use Magento\Framework\Option\ArrayInterface;

/**
 * Safecharge Safecharge payment solution source model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class PaymentSolution implements ArrayInterface
{
    /**
     * Possible actions on order place.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Payment::SOLUTION_INTERNAL,
                'label' => __('Built In Form'),
            ],
            [
                'value' => Payment::SOLUTION_EXTERNAL,
                'label' => __('Redirect'),
            ],
        ];
    }
}
