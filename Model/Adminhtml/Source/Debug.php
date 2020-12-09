<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Safecharge Safecharge mode source model.
 */
class Debug implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            2 => __('Split the log by days'),
			1 => __('Save in single file'),
			0 => __('No'),
        ];
    }
}
