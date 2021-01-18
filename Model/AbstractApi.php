<?php

namespace Nuvei\Payments\Model;

use Nuvei\Payments\Model\Logger as SafechargeLogger;

/**
 * Nuvei Payments abstract api model.
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
