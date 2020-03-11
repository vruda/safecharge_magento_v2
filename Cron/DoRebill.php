<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Safecharge\Safecharge\Cron;

use Safecharge\Safecharge\Model\Config as ModuleConfig;

class DoRebill {
	
	private $config;
	
	public function __construct(ModuleConfig $config) {
		$this->config = $config;
	}
	
	public function execute() {
		// test log
		$this->config->createLog(
			[
				'date-time' => date('Y-m-d H:i:s', time())
			],
			'DoRebill Cron Log'
		);
	}
	
}
