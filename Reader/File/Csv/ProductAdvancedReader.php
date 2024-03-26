<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv;

use Akeneo\Tool\Component\Batch\Item\FileInvalidItem;
use Akeneo\Tool\Component\Batch\Item\InitializableInterface;
use Akeneo\Tool\Component\Batch\Item\InvalidItemException;
use Akeneo\Tool\Component\Batch\Job\BatchStatus;
use Akeneo\Tool\Component\Batch\Job\ExitStatus;
use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\ImportMapping;
use ClickAndMortar\AdvancedCsvConnectorBundle\Helper\ImportHelper;
use Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Akeneo\Tool\Component\Connector\Exception\DataArrayConversionException;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Reader\File\Csv\ProductReader;
use Akeneo\Tool\Component\Connector\Reader\File\FileIteratorFactory;
use Akeneo\Tool\Component\Connector\Reader\File\MediaPathTransformer;
use ClickAndMortar\AdvancedCsvConnectorBundle\Reader\MultiFilesReaderInterface;
use Pim\Bundle\CustomEntityBundle\Entity\Repository\CustomEntityRepository;
use Doctrine\ORM\EntityRepository;

/**
 * Product advanced reader
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv
 */
class ProductAdvancedReader extends ProductReader implements InitializableInterface, MultiFilesReaderInterface
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
     * Default value key in mapping
     *
     * @var string
     */
    const MAPPING_DEFAULT_VALUE_KEY = 'defaultValue';

    /**
     * Normalizers key in mapping
     *
     * @var string
     */
    const MAPPING_NORMALIZERS_KEY = 'normalizers';

    /**
     * Complete callback mapping key
     *
     * @var string
     */
    const MAPPING_COMPLETE_CALLBACK_KEY = 'completeCallback';

    /**
     * Initialize callback mapping key
     *
     * @var string
     */
    const MAPPING_INITIALIZE_CALLBACK_KEY = 'initializeCallback';

    /**
     * Flush callback mapping key
     *
     * @var string
     */
    const MAPPING_FLUSH_CALLBACK_KEY = 'flushCallback';

    /**
     * Complete callback mapping key
     *
     * @var string
     */
    const MAPPING_ONLY_ON_CREATION_KEY = 'onlyOnCreation';

    /**
     * Identifier mapping key
     *
     * @var string
     */
    const MAPPING_IDENTIFIER_KEY = 'identifier';

    /**
     * Max length mapping key
     */
    const MAPPING_MAX_LENGTH_KEY = 'maxLength';

    /**
     * Delete if null key
     *
     * @var string
     */
    const MAPPING_DELETE_IF_NULL = 'deleteIfNull';

    /**
     * Lua updater code
     *
     * @var string
     */
    const MAPPING_LUA_UPDATER = 'luaUpdater';

    /**
     * Only update mapping parameter
     *
     * @var string
     */
    const MAPPING_ONLY_UPDATE = 'onlyUpdate';

    /**
     * LUA script prefix used to limit functions
     *
     * @var string
     */
    const LUA_SCRIPT_PREFIX = 'local _ENV = { attributeValue = attributeValue, string = string, math = math, ipairs = ipairs, load = load, next = next, pairs = pairs, rawequal = rawequal, rawget = rawget, rawlen = rawlen, rawset = rawset, select = select, tonumber = tonumber, tostring = tostring, type = type}';

    /**
     * Import helper
     *
     * @var ImportHelper
     */
    protected $importHelper;

    /**
     * Product repository
     *
     * @var EntityRepository
     */
    protected $productRepository;

    /**
     * Import mapping repository
     *
     * @var ImportMappingRepository
     */
    protected $importMappingRepository;

    /**
     * Lua updater repository
     *
     * @var LuaUpdaterRepository
     */
    protected $luaUpdaterRepository;

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
     * CSV file paths to archive
     *
     * @var array
     */
    protected $toArchiveFilesPaths = [];

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
     * Identifier attribute code
     *
     * @var null
     */
    protected $identifierCode = null;

    /**
     * Loaded LUA updaters
     *
     * @var LuaUpdater[]
     */
    protected $luaUpdaters = [];

    /**
     * Product advanced reader constructor.
     *
     * @param FileIteratorFactory     $fileIteratorFactory
     * @param ArrayConverterInterface $converter
     * @param MediaPathTransformer    $mediaPathTransformer
     * @param ImportHelper            $importHelper
     * @param EntityRepository        $productRepository
     * @param ImportMappingRepository $importMappingRepository
     * @param ImportMappingRepository $luaUpdaterRepository
     * @param array                   $options
     */
    public function __construct(
        FileIteratorFactory $fileIteratorFactory,
        ArrayConverterInterface $converter,
        MediaPathTransformer $mediaPathTransformer,
        ImportHelper $importHelper,
        EntityRepository $productRepository,
        CustomEntityRepository $importMappingRepository,
        CustomEntityRepository $luaUpdaterRepository,
        array $options = []
    )
    {
        parent::__construct($fileIteratorFactory, $converter, $mediaPathTransformer, $options);

        $this->importHelper            = $importHelper;
        $this->productRepository       = $productRepository;
        $this->importMappingRepository = $importMappingRepository;
        $this->luaUpdaterRepository    = $luaUpdaterRepository;
    }

    /**
     * Get files paths
     *
     * @return void
     */
    public function initialize()
    {
        $jobParameters = $this->stepExecution->getJobParameters();
        $this->importHelper->setStepExecution($this->stepExecution);
        if (empty($this->mapping)) {
            $mappingCode = $jobParameters->get('mapping');
            /** @var ImportMapping $importMapping */
            $importMapping = $this->importMappingRepository->findOneBy(['code' => $mappingCode]);
            if ($importMapping === null) {
                $this->stopStepExecution('batch_jobs.csv_advanced_product_import.import.errors.no_mapping');

                return;
            }
            $this->mapping = $importMapping->getMappingAsArray();
            $this->validateMapping();
        }

        // Check for initialize callback
        if (
            isset($this->mapping[self::MAPPING_INITIALIZE_CALLBACK_KEY])
            && method_exists($this->importHelper, $this->mapping[self::MAPPING_INITIALIZE_CALLBACK_KEY])
        ) {
            $this->importHelper->{$this->mapping[self::MAPPING_INITIALIZE_CALLBACK_KEY]}($jobParameters);
        }

        if (empty($this->filesPaths)) {
            $jobFilePath                    = $jobParameters->get('storage')['file_path'];
            $fromEncoding                   = $jobParameters->get('fromEncoding');
            $this->filesPaths               = $this->importHelper->splitFiles($jobFilePath, $fromEncoding);
            $this->waitingListCsvFilesPaths = $this->filesPaths;
            $this->toArchiveFilesPaths      = $this->filesPaths;
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
     * {@inheritdoc}
     */
    public function flush()
    {
        parent::flush();

        // Check for flush callback
        if (
            isset($this->mapping[self::MAPPING_FLUSH_CALLBACK_KEY])
            && method_exists($this->importHelper, $this->mapping[self::MAPPING_FLUSH_CALLBACK_KEY])
        ) {
            $this->importHelper->{$this->mapping[self::MAPPING_FLUSH_CALLBACK_KEY]}();
        }

        $this->filesPaths = [];
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

        // Remove cells without headers
        if ($countHeaders < $countData) {
            array_splice($data, $countHeaders, $countData);
        }

        // Fill empty cells if necessary
        if ($countHeaders > $countData) {
            $missingValuesCount = $countHeaders - $countData;
            $missingValues      = array_fill(0, $missingValuesCount, '');
            $data               = array_merge($data, $missingValues);
        }
        $item = array_combine($this->fileIterator->getHeaders(), $data);
        $item = $this->updateByMapping($item);
        if ($item === null) {
            return null;
        }

        try {
            $item = $this->converter->convert($item, $this->getArrayConverterOptions());
        } catch (DataArrayConversionException $e) {
            $this->skipItemFromConversionException($item, $e);
        }

        return $item;
    }

    /**
     * Get all files paths (used by archiver)
     *
     * @return array
     */
    public function getFilePaths()
    {
        return $this->toArchiveFilesPaths;
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
        $newItem      = [];
        $isNewProduct = null;

        foreach ($this->mapping[self::MAPPING_BASE_ATTRIBUTES_KEY] as $attributeMapping) {
            $attributesCodes   = [];
            $attributesCodes[] = $attributeMapping[self::MAPPING_ATTRIBUTE_CODE_KEY];

            foreach ($attributesCodes as $attributesCode) {
                $value = null;

                // Check if attribute need to be define only on creation
                if (
                    isset($attributeMapping[self::MAPPING_ONLY_ON_CREATION_KEY])
                    && $attributeMapping[self::MAPPING_ONLY_ON_CREATION_KEY] === true
                ) {
                    // Make a request to check if is a new product
                    if ($isNewProduct === null) {
                        $isNewProduct = $this->productRepository->findOneByIdentifier($newItem[$this->identifierCode]) === null;
                    }

                    if (!$isNewProduct) {
                        continue;
                    }
                }

                // Simple mapping
                if (isset($attributeMapping[self::MAPPING_DATA_CODE_KEY])) {
                    $value = $this->importHelper->getByCode($item, $attributeMapping[self::MAPPING_DATA_CODE_KEY]);
                }

                // Default value
                if (
                    empty($value)
                    && isset($attributeMapping[self::MAPPING_DEFAULT_VALUE_KEY])
                    && $attributeMapping[self::MAPPING_DEFAULT_VALUE_KEY] !== ''
                ) {
                    $value = $attributeMapping[self::MAPPING_DEFAULT_VALUE_KEY];
                }

                // Add value in new item
                if ($value !== null) {
                    // Update value with LUA script if necessary
                    if (!empty($attributeMapping[self::MAPPING_LUA_UPDATER])) {
                        // Get linked custom entity if necessary
                        $updaterCode = $attributeMapping[self::MAPPING_LUA_UPDATER];
                        if (!array_key_exists($updaterCode, $this->luaUpdaters)) {
                            $this->luaUpdaters[$updaterCode] = $this->luaUpdaterRepository->findOneBy(['code' => $updaterCode]);
                        }

                        // Apply LUA or PHP script on value
                        if ($this->luaUpdaters[$updaterCode] !== null) {
                            $luaUpdater = $this->luaUpdaters[$updaterCode];
                            $lua        = new \Lua();
                            $lua->assign('attributeValue', $value);
                            $value = $lua->eval(sprintf(
                                "%s\n%s",
                                self::LUA_SCRIPT_PREFIX,
                                $luaUpdater->getScript()
                            ));
                        } elseif (method_exists($this->importHelper, $updaterCode)) {
                            $value = $this->importHelper->{$updaterCode}($value, $attributesCode);
                        }
                    }

                    // Check if we have max length for value
                    if (isset($attributeMapping[self::MAPPING_MAX_LENGTH_KEY])) {
                        $value = mb_substr($value, 0, $attributeMapping[self::MAPPING_MAX_LENGTH_KEY]);
                    }

                    // Don't add value if value is null
                    if (
                        isset($attributeMapping[self::MAPPING_DELETE_IF_NULL])
                        && $attributeMapping[self::MAPPING_DELETE_IF_NULL] === true
                        && $value === null
                    ) {
                        continue;
                    }

                    $newItem[$attributesCode] = $value;
                }
            }
        }

        // Check for complete callback
        if ($isNewProduct === null) {
            $isNewProduct = $this->productRepository->findOneByIdentifier($newItem[$this->identifierCode]) === null;
        }

        // Manage only update
        if (
            $isNewProduct === true
            && $this->mapping[self::MAPPING_ONLY_UPDATE] === true
        ) {
            $this->throwInvalidItemException($item, 'batch_jobs.csv_advanced_product_import.import.warnings.new_product', ['%identifier%' => $newItem[$this->identifierCode]]);
        }

        if (
            isset($this->mapping[self::MAPPING_COMPLETE_CALLBACK_KEY])
            && method_exists($this->importHelper, $this->mapping[self::MAPPING_COMPLETE_CALLBACK_KEY])
        ) {
            $newItem = $this->importHelper->{$this->mapping[self::MAPPING_COMPLETE_CALLBACK_KEY]}($newItem, $isNewProduct, $item);
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

            // And set identifier
            if (
                isset($attributeMapping[self::MAPPING_IDENTIFIER_KEY])
                && $attributeMapping[self::MAPPING_IDENTIFIER_KEY] === true
            ) {
                $this->identifierCode = $attributeMapping[self::MAPPING_ATTRIBUTE_CODE_KEY];
            }
        }

        // Check identifier
        if ($this->identifierCode === null) {
            $this->stopStepExecution('batch_jobs.csv_advanced_product_import.import.errors.mapping_no_identifier');

            return false;
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
