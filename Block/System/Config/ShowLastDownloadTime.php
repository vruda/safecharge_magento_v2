<?php

namespace Nuvei\Payments\Block\System\Config;

class ShowLastDownloadTime implements \Magento\Config\Model\Config\CommentInterface
{
    private $config;
    private $directory;
    private $file;
    
    public function __construct(
        \Nuvei\Payments\Model\Config $config,
        \Magento\Framework\Filesystem\DirectoryList $directory,
        \Magento\Framework\Filesystem\Io\File $file
    ) {
        $this->config       = $config;
        $this->directory    = $directory;
        $this->file         = $file;
    }

    public function getCommentText($elementValue)  //the method has to be named getCommentText
    {
        $text       = '';
        $file       = $this->directory->getPath('tmp') . DIRECTORY_SEPARATOR . $this->config::PAYMENT_PLANS_FILE_NAME;
        $file_data  = $this->file->ls($file);
        
        if ($this->file->fileExists($file)) {
            $text = __('Last download: ') . json_encode($file_data);
        }
        
        return $text;
    }
}
