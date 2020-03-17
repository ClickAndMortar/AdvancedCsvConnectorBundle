<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File\Csv;


/**
 * Write product model data into a csv file by reading JSON mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File\Csv
 */
class ProductModelAdvancedWriter extends ProductAdvancedWriter
{
    /**
     * {@inheritdoc}
     */
    protected function getItemIdentifier(array $productModel)
    {
        return $productModel['code'];
    }
}
