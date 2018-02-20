<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv;

use Akeneo\Component\Batch\Item\FileInvalidItem;
use Akeneo\Component\Batch\Item\InitializableInterface;
use Akeneo\Component\Batch\Item\InvalidItemException;
use ClickAndMortar\AdvancedCsvConnectorBundle\Helper\ImportHelper;
use Pim\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Pim\Component\Connector\Exception\DataArrayConversionException;
use Pim\Component\Connector\Reader\File\Csv\ProductReader;
use Pim\Component\Connector\Reader\File\FileIteratorFactory;
use Pim\Component\Connector\Reader\File\MediaPathTransformer;

/**
 * Product advanced reader
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv
 */
class ProductAdvancedReader extends ProductReader implements InitializableInterface
{
    /**
     * Import helper
     *
     * @var ImportHelper
     */
    protected $importHelper;

    /**
     * All CSV file paths
     *
     * @var array
     */
    protected $filesPaths = [];

    /**
     * Waiting list for CSV files process
     *
     * @var array
     */
    protected $waitingListCsvFilesPaths = [];

    /**
     * Current file path
     *
     * @var string
     */
    protected $currentFilePath = null;

    /**
     * Attributes mapping
     *
     * @var array
     */
    protected $mapping = [];

    /**
     * Product advanced reader constructor.
     *
     * @param FileIteratorFactory     $fileIteratorFactory
     * @param ArrayConverterInterface $converter
     * @param MediaPathTransformer    $mediaPathTransformer
     * @param array                   $options
     * @param ImportHelper            $importHelper
     */
    public function __construct(
        FileIteratorFactory $fileIteratorFactory,
        ArrayConverterInterface $converter,
        MediaPathTransformer $mediaPathTransformer,
        array $options = [],
        ImportHelper $importHelper
    )
    {
        parent::__construct($fileIteratorFactory, $converter, $mediaPathTransformer, $options);

        $this->importHelper = $importHelper;
    }

    /**
     * Get files paths
     *
     * @return void
     */
    public function initialize()
    {
        $jobParameters = $this->stepExecution->getJobParameters();
        if (empty($this->filesPaths)) {
            $jobFilePath                    = $jobParameters->get('filePath');
            $this->filesPaths               = $this->importHelper->splitFiles($jobFilePath);
            $this->waitingListCsvFilesPaths = $this->filesPaths;
        }

        if (empty($this->mapping)) {
            $mappingAsJson = $jobParameters->get('mapping');
            $this->mapping = json_decode($mappingAsJson, true);
        }

        if (!empty($this->waitingListCsvFilesPaths)) {
            $this->currentFilePath = array_shift($this->waitingListCsvFilesPaths);
            $delimiter             = $jobParameters->get('delimiter');
            $enclosure             = $jobParameters->get('enclosure');
            $defaultOptions        = [
                'reader_options' => [
                    'fieldDelimiter' => $delimiter,
                    'fieldEnclosure' => $enclosure,
                ],
            ];
            $this->fileIterator    = $this->fileIteratorFactory->create(
                $this->currentFilePath,
                array_merge($defaultOptions, $this->options)
            );
            $this->fileIterator->rewind();
        }
    }

    /**
     * Read files with JSON mapping on job
     *
     * @return array
     */
    public function read()
    {
        // Read only if we have files
        if (empty($this->filesPaths)) {
            return null;
        }

        $this->fileIterator->next();
        if ($this->fileIterator->valid() && null !== $this->stepExecution) {
            $this->stepExecution->incrementSummaryInfo('item_position');
        }

        $data = $this->fileIterator->current();
        if (is_null($data)) {
            if (!empty($this->waitingListCsvFilesPaths)) {
                // Read files from waiting list
                $this->initialize();

                return $this->read();
            } else {
                return null;
            }
        }

        $headers      = $this->fileIterator->getHeaders();
        $countHeaders = count($headers);
        $countData    = count($data);
        $this->checkColumnNumber($countHeaders, $countData, $data, $this->currentFilePath);
        if ($countHeaders > $countData) {
            $missingValuesCount = $countHeaders - $countData;
            $missingValues      = array_fill(0, $missingValuesCount, '');
            $data               = array_merge($data, $missingValues);
        }
        $item = array_combine($this->fileIterator->getHeaders(), $data);
        $item = $this->updateByMapping($item);

        try {
            $item = $this->converter->convert($item, $this->getArrayConverterOptions());
        } catch (DataArrayConversionException $e) {
            $this->skipItemFromConversionException($item, $e);
        }

        return $item;
    }

    /**
     * Get all files paths
     *
     * @return array
     */
    public function getFilePaths()
    {
        return $this->filesPaths;
    }

    /**
     * Update item by job mapping
     *
     * @param array $item
     *
     * @return array
     * @throws InvalidItemException
     */
    protected function updateByMapping($item)
    {
        $newItem = [];
        foreach ($this->mapping['attributes'] as $attributeMapping) {
            $value = null;

            // Simple mapping
            if (isset($attributeMapping['dataCode'])) {
                $value = $this->importHelper->getByCode($item, $attributeMapping['dataCode']);
            }

            // Add value in new item
            if ($value !== null) {
                // Update value by callback method if necessary
                if (isset($attributeMapping['callback'])) {
                    if (method_exists($this->importHelper, $attributeMapping['callback'])) {
                        $value = $this->importHelper->{$attributeMapping['callback']}($value, $attributeMapping['attributeCode']);
                    } else {
                        $this->throwInvalidItemException($item, 'batch_jobs.csv_advanced_product_import.import.warnings.no_callback_method', ['%callbackMethod%' => $attributeMapping['callback']]);
                    }
                }

                $newItem[$attributeMapping['attributeCode']] = $value;
            }
        }

        return $newItem;
    }

    /**
     * Throw an invalid item exception
     *
     * @param array  $item
     * @param string $message
     * @param array  $parameters
     *
     * @return void
     * @throws InvalidItemException
     */
    protected function throwInvalidItemException($item, $message, $parameters = [])
    {
        $invalidItem = new FileInvalidItem(
            $item,
            ($this->stepExecution->getSummaryInfo('item_position'))
        );
        throw new InvalidItemException($message, $invalidItem, $parameters);
    }
}
