<?php

namespace Nuvei\Payments\Ui\Component\Listing\Column;

use Nuvei\Payments\Ui\Component\Listing\Column\Type\Json;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Nuvei Payments request column handler.
 */
class Request extends Column
{
    const NAME = 'request';

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
            $itemData['response'] = $this->outputFormatter
                ->formatOutputHtml($itemData['response']);
        }

        return $dataSource;
    }
}
