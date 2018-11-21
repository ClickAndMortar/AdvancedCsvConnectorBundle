<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Reader;

/**
 * Reader that handle multiple input files
 *
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Reader
 */
interface MultiFilesReaderInterface
{
    /**
     * Get all files paths
     *
     * @return array
     */
    public function getFilePaths();
}
