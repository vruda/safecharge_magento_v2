<?php

namespace Safecharge\Safecharge\Model\ResourceModel\RequestLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Safecharge Safecharge request log collection model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Collection extends AbstractCollection
{
    /**
     * Resource model construct that should be used for object initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $this->_init(
            \Safecharge\Safecharge\Model\RequestLog::class,
            \Safecharge\Safecharge\Model\ResourceModel\RequestLog::class
        );
    }
}
