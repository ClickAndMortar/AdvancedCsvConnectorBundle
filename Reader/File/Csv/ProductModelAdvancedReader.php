<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv;

/**
 * Product model advanced reader
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv
 */
class ProductModelAdvancedReader extends ProductAdvancedReader
{
    /**
     * @return array
     */
    protected function getArrayConverterOptions(): array
    {
        $jobParameters = $this->stepExecution->getJobParameters();

        return [
            // for the array converters
            'mapping'           => [
                $jobParameters->get('familyVariantColumn') => 'family_variant',
                $jobParameters->get('categoriesColumn')    => 'categories',
            ],
            'with_associations' => false,

            // for the delocalization
            'decimal_separator' => $jobParameters->get('decimalSeparator'),
            'date_format'       => $jobParameters->get('dateFormat'),
        ];
    }
}
