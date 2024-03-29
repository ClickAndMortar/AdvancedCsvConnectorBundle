<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File;

use Akeneo\Pim\Enrichment\Component\Product\Connector\ProductColumnSorter;

/**
 * Reorder columns by mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File
 */
class ProductColumnSorterByMapping extends ProductColumnSorter
{
    /**
     * Columns order context key
     *
     * @var string
     */
    const CONTEXT_KEY_COLUMNS_ORDER = 'columnsOrder';

    /**
     * {@inheritdoc}
     */
    public function sort(array $columns, array $context = [])
    {
        // Get order from context if possible
        if (isset($context[self::CONTEXT_KEY_COLUMNS_ORDER])) {
            $sortedColumns = [];
            foreach ($context[self::CONTEXT_KEY_COLUMNS_ORDER] as $columnName) {
                if (in_array($columnName, $columns)) {
                    $sortedColumns[] = $columnName;
                }
            }

            // Add other columns
            $otherColumns = array_diff($columns, $sortedColumns);
            if (!empty($otherColumns)) {
                $sortedColumns = array_merge($sortedColumns, $otherColumns);
            }

            return $sortedColumns;
        }

        // Else keep classic case
        return parent::sort($columns, $context);
    }
}
