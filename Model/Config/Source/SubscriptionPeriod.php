<?php

namespace Nuvei\Payments\Model\Config\Source;

class SubscriptionPeriod extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    public function getAllOptions()
    {
        $options = [];
        
        for($i = 0; $i <= 100; $i++) {
            $options[] = [
                'label' => (string) $i,
                'value' => (string) $i
            ];
        }
        
        $this->_options = $options;
        
        return $this->_options;
    }
}
