<?php

namespace Safecharge\Safecharge\Ui\Component\Listing\Column\Type;

/**
 * Safecharge Safecharge column type data formatter.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Json
{
    /**
     * @param array|string $itemData
     * @param int $level
     *
     * @return string
     */
    public function formatOutputHtml($itemData, $level = 0)
    {
        if (is_string($itemData)) {
            $itemData = json_decode($itemData, 1);
        }
        if ($itemData === null) {
            return '';
        }

        $html = '<ul style="margin-left:' . $level . 'px;">';
        foreach ($itemData as $key => $value) {
            $html .= '<li>';
            if (is_array($value)) {
                $html .= $key . ': ' . $this->formatOutputHtml($value, $level + 20);
            } else {
                if (strlen($value) > 64) {
                    $value = substr($value, 0, 64) . '...';
                }
                $html .= $key . ': ' . $value;
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return utf8_encode($html);
    }
}
