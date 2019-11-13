<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

/**
 * Safecharge Safecharge cc type source model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class CcType extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * List of specific credit card types.
     *
     * @var array
     */
    private $specificCardTypesList = [];

    /**
     * Allowed credit card types.
     *
     * @return string[]
     */
    public function getAllowedTypes()
    {
        return array_merge_recursive(
            parent::getAllowedTypes(),
            ['VI', 'MC', 'AE', 'MI', 'DN']
        );
    }

    /**
     * Returns credit cards types
     *
     * @return array
     */
    public function getCcTypeLabelMap()
    {
        return array_merge(
            $this->specificCardTypesList,
            $this->_paymentConfig->getCcTypes()
        );
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $allowed = $this->getAllowedTypes();
        $options = [];

        foreach ($this->getCcTypeLabelMap() as $code => $name) {
            if (in_array($code, $allowed, true)) {
                $options[] = ['value' => $code, 'label' => $name];
            }
        }

        return $options;
    }
}
