<?php

namespace Nuvei\Payments\Cron;

use Nuvei\Payments\Model\Config as ModuleConfig;

/**
 * Nuvei Payments delete old request log entries cron job.
 */
class CheckForLatestVersion
{
    /**
     * @var ModuleConfig
     */
    private $moduleConfig;


    /**
     *
     * @param ModuleConfig $moduleConfig
     */
    public function __construct(
        ModuleConfig $moduleConfig
    ) {
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @return CheckForLatestVersion
     */
    public function execute()
    {
        if ($this->moduleConfig->isActive() === false) {
            return $this;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://raw.githubusercontent.com/octocat/Spoon-Knife/master/index.html');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);
        
        
        

        return $this;
    }
}
