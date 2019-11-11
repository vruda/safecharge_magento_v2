<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Safecharge Safecharge payment action source model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
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
			'sha256'	=> 'SHA 256',
			'md5'		=> 'MD 5',
        ];
    }
}
