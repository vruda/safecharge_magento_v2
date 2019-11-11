<?php

namespace Safecharge\Safecharge\Model;

use Safecharge\Safecharge\Model\Logger as SafechargeLogger;

/**
 * Safecharge Safecharge abstract api model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
abstract class AbstractApi
{
    /**
     * @var SafechargeLogger
     */
    protected $safechargeLogger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * Object initialization.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config           $config
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config
    ) {
        $this->safechargeLogger = $safechargeLogger;
        $this->config = $config;
    }
}
