<?php

/**
 * Description of ZeroTotal
 * Override original logic in Magento 2 and allow Orders with Total Amount of 0
 *
 * @author Safecharge
 */

namespace Safecharge\Safecharge\Plugin;

class ZeroTotal {
	public function afterIsApplicable($subject, $result) {
        return true;
    }
}
