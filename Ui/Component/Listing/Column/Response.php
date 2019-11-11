<?php

namespace Safecharge\Safecharge\Ui\Component\Listing\Column;

use Safecharge\Safecharge\Ui\Component\Listing\Column\Type\Json;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Safecharge Safecharge response column handler.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Response extends Column
{
    const NAME = 'response';

    /**
     * @var Json
     */
    private $outputFormatter;

    /**
     * Response constructor.
     *
     * @param ContextInterface   $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Json               $outputFormatter
     * @param array              $components
     * @param array              $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Json $outputFormatter,
        array $components = [],
        array $data = []
    ) {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );

        $this->outputFormatter = $outputFormatter;
    }

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        foreach ($dataSource['data']['items'] as &$itemData) {
            $itemData['request'] = $this->outputFormatter
                ->formatOutputHtml($itemData['request']);
        }

        return $dataSource;
    }
}
