<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File;

use Akeneo\Tool\Component\Connector\Writer\File\ColumnSorterInterface;
use Akeneo\Pim\Enrichment\Component\Product\Connector\ProductColumnSorter;

/**
 * Product column sorter by mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File
 */
class ProductColumnSorterByMapping extends ProductColumnSorter implements ColumnSorterInterface
{
    /**
     * Main mapping key
     *
     * @var string
     */
    const MAPPING_KEY_MAIN = 'mapping';

    /**
     * Columns order mapping key
     *
     * @var string
     */
    const MAPPING_KEY_COLUMNS_ORDER = 'columnsOrder';

    /**
     * {@inheritdoc}
     */
    public function sort(array $columns, array $context = [])
    {
        // Get order from mapping if possible
        if (isset($context[self::MAPPING_KEY_MAIN])) {
            $mapping = json_decode($context[self::MAPPING_KEY_MAIN], true);
            if ($mapping !== null && isset($mapping[self::MAPPING_KEY_COLUMNS_ORDER])) {
                $sortedColumns = [];
                foreach ($mapping[self::MAPPING_KEY_COLUMNS_ORDER] as $columnName) {
                    if (in_array($columnName, $columns)) {
                        $sortedColumns[] = $columnName;
                    }
                }

                return $sortedColumns;
            }
        }

        // Else keep classic case
        return parent::sort($columns);
    }
}
