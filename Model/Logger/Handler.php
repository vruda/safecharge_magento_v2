<?php

namespace Safecharge\Safecharge\Model\Logger;

use Magento\Framework\Logger\Handler\Base;

/**
 * Safecharge Safecharge logger handler model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Handler extends Base
{
    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/safecharge_safecharge.log';
}
