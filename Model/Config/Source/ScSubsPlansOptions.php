<?php

namespace Safecharge\Safecharge\Model\Config\Source;

class ScSubsPlansOptions extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
	private $directory;
	private $config;
	
	public function __construct(
		\Magento\Framework\Filesystem\DirectoryList $directory,
		\Safecharge\Safecharge\Model\Config $config
	)
	{
		$this->directory = $directory;
		$this->config = $config;
	}
	
	public function getAllOptions()
    {
		$this->_options[] = [
			'label' => 'No Plan',
			'value' => ''

		];

		# json version
		$file_name = $this->directory->getPath('tmp') . DIRECTORY_SEPARATOR . 'sc_subscriptions.json';
		
		try {
			$fp = fopen($file_name, "r");
			$cont = json_decode(fread($fp, filesize($file_name)), true);
			fclose($fp);

			if(is_array($cont) && !empty($cont['plans'])) {
				foreach($cont['plans'] as $data) {
					$this->_options[] = [
						'label' => $data['name'],
						'value' => $data['planId']

					];
				}
			}
		}
		catch (Exception $e) {
			$this->config->createLog($e->getMessage(), 'ScSubsPlansOptions Exception');
		}
		# json version END

		return $this->_options;
    }
}