<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Safecharge Safecharge payment action source model.
 */
class Hash implements ArrayInterface
{
    /**
     * Possible actions on order place.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
			''			=> __('Please, select an option...'),
            'sha256'	=> 'SHA 256',
            'md5'       => 'MD 5',
        ];
    }
}
