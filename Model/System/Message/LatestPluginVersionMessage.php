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
        return true;
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
