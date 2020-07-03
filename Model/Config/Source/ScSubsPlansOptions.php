<?php

namespace Safecharge\Safecharge\Model\Config\Source;

class ScSubsPlansOptions extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    protected $eavConfig;
    
    private $directory;
    private $config;
    
    public function __construct(
        \Magento\Framework\Filesystem\DirectoryList $directory,
        \Safecharge\Safecharge\Model\Config $config
    ) {
        $this->directory = $directory;
        $this->config = $config;
    }
    
    public function getAllOptions()
    {
        $this->_options[] = [
            'label' => __('No Plan'),
            'value' => 0

        ];
        
        # json version
        $file_name = $this->directory->getPath('tmp') . DIRECTORY_SEPARATOR . 'sc_subscriptions.json';
        
        if (is_readable($file_name)) {
            try {
                $fp = fopen($file_name, "r");
                $cont = json_decode(fread($fp, filesize($file_name)), true);
                fclose($fp);

                if (!empty($cont['plans']) && is_array($cont['plans'])) {
                    foreach ($cont['plans'] as $data) {
                        $this->_options[] = [
                            'label' => $data['name'],
                            'value' => $data['planId']

                        ];
                    }
                }
            } catch (Exception $e) {
                $this->config->createLog($e->getMessage(), 'ScSubsPlansOptions Exception');
            }
        } elseif (file_exists($file_name)) {
            $this->config->createLog('ScSubsPlansOptions Error - ' . $file_name . ' exists, but is not readable.');
        } else {
            $this->config->createLog('ScSubsPlansOptions - ' . $file_name . ' does not exists.');
        }
        # json version END

        return $this->_options;
    }
}
