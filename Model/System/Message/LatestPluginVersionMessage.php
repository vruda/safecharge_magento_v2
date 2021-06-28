<?php

namespace Nuvei\Payments\Model\System\Message;

/**
 * Show System message if there is new version of the plugin,
 *
 * @author Nuvei
 */
class LatestPluginVersionMessage implements \Magento\Framework\Notification\MessageInterface
{
    const MESSAGE_IDENTITY = 'nuvei_plugin_version_message';
    
    private $directory;
    private $modulConfig;
    
    public function __construct(
        \Magento\Framework\Filesystem\DirectoryList $directory,
        \Nuvei\Payments\Model\Config $modulConfig
    ) {
        $this->directory    = $directory;
        $this->modulConfig  = $modulConfig;
    }

    /**
    * Retrieve unique system message identity
    *
    * @return string
    */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }
    
    /**
    * Check whether the system message should be shown
    *
    * @return bool
    */
    public function isDisplayed()
    {
        $file = $this->directory->getPath('log') . DIRECTORY_SEPARATOR . 'nuvei-plugin-latest-version.txt';
        
        if(!is_readable($file)) {
            return false;
        }
        
        $git_version = (int) str_replace('.', '', trim(file_get_contents($file)));
        
        $this_version = str_replace('Magento Plugin ', '', $this->modulConfig->getSourcePlatformField());
        $this_version = (int) str_replace('.', '', $this_version);
        
        if($git_version > $this_version) {
            return true;
        }
        
        return false;
    }
    
    /**
    * Retrieve system message text
    *
    * @return \Magento\Framework\Phrase
    */
    public function getText()
    {
        return __('A new version of Nuvei Plugin is available in the GIT repo. Please, consider to upgrade!');
    }
    
    /**
    * Retrieve system message severity
    * Possible default system message types:
    * - MessageInterface::SEVERITY_CRITICAL
    * - MessageInterface::SEVERITY_MAJOR
    * - MessageInterface::SEVERITY_MINOR
    * - MessageInterface::SEVERITY_NOTICE
    *
    * @return int
    */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
