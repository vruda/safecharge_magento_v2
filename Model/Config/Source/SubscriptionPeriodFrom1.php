<?php

namespace Nuvei\Payments\Model\Config\Source;

class SubscriptionPeriodFrom1 extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    public function getAllOptions()
    {
        $options = [];
        
        for($i = 1; $i <= 60; $i++) {
            $options[] = [
                'label' => (string) $i,
                'value' => (string) $i
            ];
        }
        
        $this->_options = $options;
        
        return $this->_options;
    }
}
