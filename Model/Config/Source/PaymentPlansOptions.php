<?php

namespace Nuvei\Payments\Model\Config\Source;

class PaymentPlansOptions extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
//    protected $eavConfig;
    
    private $directory;
    private $config;
    
    public function __construct(
        \Magento\Framework\Filesystem\DirectoryList $directory,
        \Nuvei\Payments\Model\Config $config
    ) {
        $this->directory = $directory;
        $this->config = $config;
    }
    
    public function getAllOptions()
    {
        $this->_options[] = [
            'label' => __('No Plan'),
            'value' => 1 // need to be greater than 0

        ];
        
        # json version
        $file_name = $this->directory->getPath('tmp') . DIRECTORY_SEPARATOR
			. \Nuvei\Payments\Model\Config::PAYMENT_PLANS_FILE_NAME;
        
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
                $this->config->createLog($e->getMessage(), 'PaymentPlansOptions Exception');
            }
        } elseif (file_exists($file_name)) {
            $this->config->createLog('PaymentPlansOptions Error - ' . $file_name . ' exists, but is not readable.');
        } else {
            $this->config->createLog('PaymentPlansOption - ' . $file_name . ' does not exists.');
        }
        # json version END

        return $this->_options;
    }
}
