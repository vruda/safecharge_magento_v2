<?php

namespace Safecharge\Safecharge\Model\Logger;

use Magento\Framework\Logger\Handler\Base;

/**
 * Safecharge Safecharge logger handler model.
 */
class Handler extends Base
{
    public function __construct(
        \Magento\Framework\Filesystem\DriverInterface $filesystem,
        $filePath = null,
        $fileName = null
    ) {
        $fileName = '/var/log/safecharge_'. date('Y-m-d', time()) .'.log';
        
        parent::__construct($filesystem, $filePath, $fileName);
    }
}
