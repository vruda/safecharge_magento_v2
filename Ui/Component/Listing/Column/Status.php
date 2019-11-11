<?php

namespace Safecharge\Safecharge\Ui\Component\Listing\Column;

use Safecharge\Safecharge\Model\AbstractResponse;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Safecharge Safecharge status column handler.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Status extends Column
{
    const NAME = 'status';

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        foreach ($dataSource['data']['items'] as &$itemData) {
            $itemData['status'] = $this->formatOutputHtml($itemData['status']);
        }

        return $dataSource;
    }

    /**
     * @param int $status
     *
     * @return string
     */
    private function formatOutputHtml($status)
    {
        $class = '';
        $text = '';

        switch ((int)$status) {
            case AbstractResponse::STATUS_FAILED:
                $class = 'grid-severity-critical';
                $text = __('Failed');
                break;
            case AbstractResponse::STATUS_SUCCESS:
                $class = 'grid-severity-notice';
                $text = __('Success');
                break;
        }

        return '<span class="' . $class . '"><span>' . $text . '</span></span>';
    }
}
