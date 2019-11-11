<?php

namespace Safecharge\Safecharge\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Safecharge Safecharge request log resource model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class RequestLog extends AbstractDb
{
    /**
     * Resource model construct that should be used for object initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('safecharge_safecharge_api_request_log_grid', 'request_id');
    }
}
