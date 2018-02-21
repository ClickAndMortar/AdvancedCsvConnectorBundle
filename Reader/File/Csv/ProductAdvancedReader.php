<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv;

use Akeneo\Component\Batch\Item\FileInvalidItem;
use Akeneo\Component\Batch\Item\InitializableInterface;
use Akeneo\Component\Batch\Item\InvalidItemException;
use Akeneo\Component\Batch\Job\BatchStatus;
use Akeneo\Component\Batch\Job\ExitStatus;
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
     * Mapping base attributes key
     *
     * @var string
     */
    const MAPPING_BASE_ATTRIBUTES_KEY = 'attributes';

    /**
     * Attribute code key for each attribute in mapping
     *
     * @var string
     */
    const MAPPING_ATTRIBUTE_CODE_KEY = 'attributeCode';

    /**
     * Column code key in mapping
     *
     * @var string
     */
    const MAPPING_DATA_CODE_KEY = 'dataCode';

    /**
     * Callback key in mapping
     *
     * @var string
     */
    const MAPPING_CALLBACK_KEY = 'callback';

    /**
     * Default value key in mapping
     *
     * @var string
     */
    const MAPPING_DEFAULT_VALUE_KEY = 'defaultValue';

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
            $this->validateMapping();
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
        foreach ($this->mapping[self::MAPPING_BASE_ATTRIBUTES_KEY] as $attributeMapping) {
            $value = null;

            // Simple mapping
            if (isset($attributeMapping[self::MAPPING_DATA_CODE_KEY])) {
                $value = $this->importHelper->getByCode($item, $attributeMapping[self::MAPPING_DATA_CODE_KEY]);
            }

            // Default value
            if (empty($value) && isset($attributeMapping[self::MAPPING_DEFAULT_VALUE_KEY])) {
                $value = $attributeMapping[self::MAPPING_DEFAULT_VALUE_KEY];
            }

            // Add value in new item
            if ($value !== null) {
                // Update value by callback method if necessary
                if (isset($attributeMapping[self::MAPPING_CALLBACK_KEY])) {
                    if (method_exists($this->importHelper, $attributeMapping[self::MAPPING_CALLBACK_KEY])) {
                        $value = $this->importHelper->{$attributeMapping[self::MAPPING_CALLBACK_KEY]}($value, $attributeMapping[self::MAPPING_ATTRIBUTE_CODE_KEY]);
                    } else {
                        $this->throwInvalidItemException($item, 'batch_jobs.csv_advanced_product_import.import.warnings.no_callback_method', ['%callbackMethod%' => $attributeMapping[self::MAPPING_CALLBACK_KEY]]);
                    }
                }

                $newItem[$attributeMapping[self::MAPPING_ATTRIBUTE_CODE_KEY]] = $value;
            }
        }

        return $newItem;
    }

    /**
     * Validate current mapping
     *
     * @return bool
     */
    protected function validateMapping()
    {
        // Check for main attributes key
        if (!isset($this->mapping[self::MAPPING_BASE_ATTRIBUTES_KEY])) {
            $this->stopStepExecution('batch_jobs.csv_advanced_product_import.import.errors.mapping_attributes_error');

            return false;
        }

        // Check each attribute mapping
        foreach ($this->mapping[self::MAPPING_BASE_ATTRIBUTES_KEY] as $attributeMapping) {
            if (!isset($attributeMapping[self::MAPPING_ATTRIBUTE_CODE_KEY])) {
                $this->stopStepExecution('batch_jobs.csv_advanced_product_import.import.errors.mapping_no_attribute_code');

                return false;
            }
        }

        return true;
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

    /**
     * Stop step execution with error message
     *
     * @param string $errorMessage
     *
     * @return void
     */
    protected function stopStepExecution($errorMessage = '')
    {
        $this->stepExecution->setStatus(new BatchStatus(BatchStatus::FAILED));
        $this->stepExecution->setExitStatus(new ExitStatus(ExitStatus::FAILED));
        $this->stepExecution->setEndTime(new \DateTime('now'));
        $this->stepExecution->addFailureException(new \Exception($errorMessage));
    }
}
