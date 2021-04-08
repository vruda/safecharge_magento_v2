<?php

namespace Nuvei\Payments\Model\Config\Source;

class SubscriptionPeriodFrom0 extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    public function getAllOptions()
    {
        $options = [];
        
        for ($i = 0; $i <= 31; $i++) {
            $options[] = [
                'label' => (string) $i,
                'value' => (string) $i
            ];
        }
        
        $this->_options = $options;
        
        return $this->_options;
    }
}
