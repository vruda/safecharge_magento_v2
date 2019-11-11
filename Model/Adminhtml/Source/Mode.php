<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

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
        return [
            'live'		=> __('Live'),
            'sandbox'	=> __('Sandbox'),
        ];
    }
}
