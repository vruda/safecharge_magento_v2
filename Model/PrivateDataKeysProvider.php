<?php

namespace Safecharge\Safecharge\Model;

/**
 * Safecharge Safecharge private data keys provider model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class PrivateDataKeysProvider
{
    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'cardNumber',
            'CVV',
        ];
    }
}
