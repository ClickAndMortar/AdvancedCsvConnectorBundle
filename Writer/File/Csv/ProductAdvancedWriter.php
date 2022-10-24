<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File\Csv;

use Akeneo\Pim\Enrichment\Component\Product\Connector\FlatTranslator\FlatTranslatorInterface;
use Akeneo\Pim\Structure\Component\Model\Attribute;
use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\Buffer\BufferFactory;
use Akeneo\Tool\Component\Connector\Writer\File\WrittenFileInfo;
use Akeneo\Tool\Component\FileStorage\FilesystemProvider;
use Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface;
use ClickAndMortar\AdvancedCsvConnectorBundle\Doctrine\ORM\Repository\ExportMappingRepository;
use ClickAndMortar\AdvancedCsvConnectorBundle\Doctrine\ORM\Repository\LuaUpdaterRepository;
use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\ExportMapping;
use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\LuaUpdater;
use ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv\ProductAdvancedReader;
use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;
use Pim\Bundle\CustomEntityBundle\Entity\AbstractTranslatableCustomEntity;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Akeneo\Tool\Component\Connector\Writer\File\AbstractItemMediaWriter;
use Akeneo\Tool\Component\Connector\Writer\File\ArchivableWriterInterface;
use Akeneo\Tool\Component\Connector\Writer\File\FileExporterPathGeneratorInterface;
use Akeneo\Tool\Component\Connector\Writer\File\FlatItemBufferFlusher;
use Akeneo\Pim\Structure\Component\Model\AttributeOption;
use ClickAndMortar\AdvancedCsvConnectorBundle\Helper\ExportHelper;
use Doctrine\ORM\EntityManager;
use Pim\Bundle\CustomEntityBundle\Entity\Repository\CustomEntityRepository;
use Symfony\Component\DependencyInjection\Container;
use ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File\ProductColumnSorterByMapping;

/**
 * Write product data into a csv file by reading JSON mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File\Csv
 */
class ProductAdvancedWriter extends AbstractItemMediaWriter implements
    ItemWriterInterface,
    StepExecutionAwareInterface,
    ArchivableWriterInterface
{
    /**
     * Column name key in mapping
     *
     * @var string
     */
    const MAPPING_COLUMN_NAME_KEY = 'columnName';

    /**
     * Forced value key in mapping
     *
     * @var string
     */
    const MAPPING_FORCE_VALUE_KEY = 'forcedValue';

    /**
     * Normalizers key in mapping
     *
     * @var string
     */
    const MAPPING_NORMALIZERS_KEY = 'normalizers';

    /**
     * Columns key in mapping
     *
     * @var string
     */
    const MAPPING_COLUMNS_KEY = 'columns';

    /**
     * Attribute code key
     *
     * @var string
     */
    const MAPPING_ATTRIBUTE_CODE_KEY = 'attributeCode';

    /**
     * Default file path
     *
     * @var string
     */
    const DEFAULT_FILE_PATH = 'filePath';

    /**
     * Use Label option key
     *
     * @var string
     */
    const MAPPING_USE_LABEL_KEY = 'useLabel';

    /**
     * Use reference data label option key
     *
     * @var string
     */
    const MAPPING_USE_REFERENCE_LABEL_KEY = 'useReferenceLabel';

    /**
     * Locale option key
     *
     * @var string
     */
    const MAPPING_LOCALE_KEY = 'locale';

    /**
     * Capitalized option key
     *
     * @var string
     */
    const MAPPING_CAPITALIZED_KEY = 'capitalized';

    /**
     * Max length key
     *
     * @var string
     */
    const MAPPING_MAX_LENGTH_KEY = 'maxLength';

    /**
     * Complete callback key
     *
     * @var string
     */
    const MAPPING_COMPLETE_CALLBACK_KEY = 'completeCallback';

    /**
     * Default value key in mapping
     *
     * @var string
     */
    const MAPPING_DEFAULT_VALUE_KEY = 'defaultValue';

    /**
     * Lua updater code
     *
     * @var string
     */
    const MAPPING_LUA_UPDATER = 'luaUpdater';

    /**
     * Export helper
     *
     * @var ExportHelper
     */
    protected $exportHelper;

    /**
     * Doctrine EntityManager
     *
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * Export mapping repository
     *
     * @var ExportMappingRepository
     */
    protected $exportMappingRepository;

    /**
     * Service container
     *
     * @var Container
     */
    protected $container;

    /**
     * Lua updater repository
     *
     * @var LuaUpdaterRepository
     */
    protected $luaUpdaterRepository;

    /**
     * Default locale
     *
     * @var string
     */
    protected $defaultLocale;

    /**
     * @var FlatTranslatorInterface
     */
    protected $flatTranslator;

    /**
     * @var FileInfoRepositoryInterface
     */
    protected $fileInfoRepository;

    /**
     * @var FilesystemProvider
     */
    protected $filesystemProvider;

    /**
     * Loaded attributes
     *
     * @var Attribute[]
     */
    protected $attributes = [];

    /**
     * Loaded LUA updaters
     *
     * @var LuaUpdater[]
     */
    protected $luaUpdaters = [];

    /**
     * @param ArrayConverterInterface            $arrayConverter
     * @param BufferFactory                      $bufferFactory
     * @param FlatItemBufferFlusher              $flusher
     * @param AttributeRepositoryInterface       $attributeRepository
     * @param FileExporterPathGeneratorInterface $fileExporterPath
     * @param FlatTranslatorInterface            $flatTranslator
     * @param array                              $mediaAttributeTypes
     * @param ExportHelper                       $exportHelper
     * @param EntityManager                      $entityManager
     * @param CustomEntityRepository             $exportMappingRepository
     * @param Container                          $container
     * @param CustomEntityRepository             $luaUpdaterRepository
     * @param string                             $defaultLocale
     * @param String                             $jobParamFilePath
     */
    public function __construct(
        ArrayConverterInterface $arrayConverter,
        BufferFactory $bufferFactory,
        FlatItemBufferFlusher $flusher,
        AttributeRepositoryInterface $attributeRepository,
        FileExporterPathGeneratorInterface $fileExporterPath,
        FlatTranslatorInterface $flatTranslator,
        FileInfoRepositoryInterface $fileInfoRepository,
        FilesystemProvider $filesystemProvider,
        array $mediaAttributeTypes,
        ExportHelper $exportHelper,
        EntityManager $entityManager,
        CustomEntityRepository $exportMappingRepository,
        Container $container,
        CustomEntityRepository $luaUpdaterRepository,
        string $defaultLocale,
        $jobParamFilePath = self::DEFAULT_FILE_PATH,
    )
    {
        parent::__construct($arrayConverter, $bufferFactory, $flusher, $attributeRepository, $fileExporterPath, $flatTranslator, $fileInfoRepository, $filesystemProvider, $mediaAttributeTypes, $jobParamFilePath);
        $this->exportHelper            = $exportHelper;
        $this->entityManager           = $entityManager;
        $this->exportMappingRepository = $exportMappingRepository;
        $this->container               = $container;
        $this->luaUpdaterRepository    = $luaUpdaterRepository;
        $this->defaultLocale           = $defaultLocale;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items): void
    {
        $parameters       = $this->stepExecution->getJobParameters();
        $mapping          = $this->getMappingFromJobParameters($parameters);
        $converterOptions = $this->getConverterOptions($parameters);
        $flatItems        = [];
        $directory        = $this->stepExecution->getJobExecution()->getExecutionContext()
                                                ->get(JobInterface::WORKING_DIRECTORY_PARAMETER);
        $localesToExport  = $parameters->get('filters')['structure']['locales'];
        foreach ($items as $item) {
            if ($parameters->has('with_media') && $parameters->get('with_media')) {
                $item = $this->resolveMediaPaths($item, $directory);
            }
            $flatItem    = $this->arrayConverter->convert($item, $converterOptions);
            $flatItem    = $this->updateItemByMapping($flatItem, $mapping, $localesToExport);
            $flatItems[] = $flatItem;
        }
        $this->flatRowBuffer->write($flatItems, ['withHeader' => $parameters->get('withHeader')]);
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->flusher->setStepExecution($this->stepExecution);
        $parameters = $this->stepExecution->getJobParameters();

        // Force Excel if necessary
        $currentFilePath = $this->getPath();
        if ($parameters->get('forceXlsx')) {
            $currentFilePath = sprintf(
                '%s/%s.xlsx',
                pathinfo($currentFilePath, PATHINFO_DIRNAME),
                pathinfo($currentFilePath, PATHINFO_FILENAME)
            );
        }

        $flatFiles = $this->flusher->flush(
            $this->flatRowBuffer,
            $this->getWriterConfiguration(),
            $currentFilePath,
            ($parameters->has('linesPerFile') ? $parameters->get('linesPerFile') : -1)
        );

        foreach ($flatFiles as $flatFile) {
            $this->writtenFiles[] = WrittenFileInfo::fromLocalFile(
                $flatFile,
                \basename($flatFile)
            );
        }

        // Update encoding if necessary
        $encoding = $parameters->get('encoding');
        if (!empty($encoding)) {
            foreach ($this->getWrittenFiles() as $filePath => $fileName) {
                $this->exportHelper->encodeFile($filePath, $encoding);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getWriterConfiguration(): array
    {
        $parameters = $this->stepExecution->getJobParameters();

        // Force Excel export if necessary
        if ($parameters->get('forceXlsx')) {
            return [
                'type' => 'xlsx'
            ];
        }

        return [
            'type'           => 'csv',
            'fieldDelimiter' => $parameters->get('delimiter'),
            'fieldEnclosure' => $parameters->get('enclosure'),
            'shouldAddBOM'   => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemIdentifier(array $product): string
    {
        return $product['identifier'];
    }

    /**
     * Return mapping as array from job parameters
     *
     * @param JobParameters $parameters
     *
     * @return array
     */
    protected function getMappingFromJobParameters(JobParameters $parameters)
    {
        $mappingCode = $parameters->get('mapping');
        /** @var ExportMapping $exportMapping */
        $exportMapping = $this->exportMappingRepository->findOneBy(['code' => $mappingCode]);
        if ($exportMapping === null) {
            $this->stopStepExecution('batch_jobs.csv_advanced_product_export.export.errors.no_mapping');

            return [];
        }

        // Set columns order from mapping
        $mappingAsArray = $exportMapping->getMappingAsArray();
        $columnsOrder   = $this->getColumnsOrderByMapping($mappingAsArray);
        $parameters->set(ProductColumnSorterByMapping::CONTEXT_KEY_COLUMNS_ORDER, $columnsOrder);

        return $mappingAsArray;
    }

    /**
     * Update item keys and values with $mapping from export job
     *
     * @param array $item
     * @param array $mapping
     * @param array $localesToExport
     *
     * @return array
     */
    protected function updateItemByMapping(array $item, array $mapping, $localesToExport = [])
    {
        $newItem = $item;
        if (isset($mapping[self::MAPPING_COLUMNS_KEY])) {
            $newItem = [];
            $locale  = $this->defaultLocale;
            foreach ($mapping[self::MAPPING_COLUMNS_KEY] as $columnMapping) {
                // Get attribute key
                if (isset($columnMapping[self::MAPPING_ATTRIBUTE_CODE_KEY])) {
                    $attributeKey = $columnMapping[self::MAPPING_ATTRIBUTE_CODE_KEY];
                } else {
                    $attributeKey = '';
                }

                // Get attribute custom key
                $attributeCustomKey = $attributeKey;
                if (
                    isset($columnMapping[self::MAPPING_COLUMN_NAME_KEY])
                    && !empty($columnMapping[self::MAPPING_COLUMN_NAME_KEY])
                ) {
                    $attributeCustomKey = $columnMapping[self::MAPPING_COLUMN_NAME_KEY];
                }

                // Check if we have additional column
                if (empty($attributeKey)) {
                    if (isset($columnMapping[self::MAPPING_FORCE_VALUE_KEY])) {
                        $newItem[$attributeCustomKey] = $columnMapping[self::MAPPING_FORCE_VALUE_KEY];
                    } else {
                        $newItem[$attributeCustomKey] = '';
                    }
                    continue;
                }

                // Check if we have key in item
                if (!array_key_exists($attributeKey, $item)) {
                    if (
                        isset($columnMapping[self::MAPPING_DEFAULT_VALUE_KEY])
                        && $columnMapping[self::MAPPING_DEFAULT_VALUE_KEY] != ''
                    ) {
                        $newItem[$attributeCustomKey] = $columnMapping[self::MAPPING_DEFAULT_VALUE_KEY];
                    } else {
                        $newItem[$attributeCustomKey] = '';
                    }
                    continue;
                }
                $attributeValue     = $item[$attributeKey];
                $attributeBaseValue = $attributeValue;

                // Force value if necessary
                if (!empty($columnMapping[self::MAPPING_FORCE_VALUE_KEY])) {
                    $attributeValue = $columnMapping[self::MAPPING_FORCE_VALUE_KEY];
                }

                // Set locale if necessary
                if (!empty($columnMapping[self::MAPPING_LOCALE_KEY])) {
                    $locale = $columnMapping[self::MAPPING_LOCALE_KEY];
                }

                // Use label instead of code in list value cases
                if (
                    !empty($columnMapping[self::MAPPING_USE_LABEL_KEY])
                    && $columnMapping[self::MAPPING_USE_LABEL_KEY] == true
                ) {
                    $attributeValue = $this->exportHelper->getValueFromCode($attributeKey, $attributeValue, $locale);

                    // Load attribute to check if we have custom entity linked
                    if (!array_key_exists($attributeKey, $this->attributes)) {
                        $this->attributes[$attributeKey] = $this->attributeRepository->findOneByIdentifier($attributeKey);
                    }
                    if (!empty($this->attributes[$attributeKey]) && !empty($this->attributes[$attributeKey]->getReferenceDataName())) {
                        $customEntityClassParameter = sprintf('pim_custom_entity.entity.%s.class', $this->attributes[$attributeKey]->getReferenceDataName());
                        if ($this->container->hasParameter($customEntityClassParameter)) {
                            $customEntityClass = $this->container->getParameter($customEntityClassParameter);
                            $attributeValue    = $this->getReferenceValueFromCode($attributeBaseValue, $locale, $customEntityClass);
                        }
                    }
                }

                // Capitalize value if necessary
                if (!empty($columnMapping[self::MAPPING_CAPITALIZED_KEY]) && $columnMapping[self::MAPPING_CAPITALIZED_KEY] == true) {
                    $attributeValue = strtoupper($attributeValue);
                }

                // Shorten value if necessary
                if (!empty($columnMapping[self::MAPPING_MAX_LENGTH_KEY]) && strlen($attributeValue) > $columnMapping[self::MAPPING_MAX_LENGTH_KEY]) {
                    $attributeValue = substr($attributeValue, 0, $columnMapping[self::MAPPING_MAX_LENGTH_KEY]);
                }

                // Set default value if necessary
                if (
                    isset($columnMapping[self::MAPPING_DEFAULT_VALUE_KEY])
                    && $columnMapping[self::MAPPING_DEFAULT_VALUE_KEY] != ''
                    && empty($attributeValue)
                ) {
                    $attributeValue = $columnMapping[self::MAPPING_DEFAULT_VALUE_KEY];
                }

                // Update value with LUA or PHP script if necessary
                if (
                    !empty($columnMapping[self::MAPPING_LUA_UPDATER])
                    && $attributeValue !== null
                ) {
                    // Get linked custom entity if necessary
                    $updaterCode = $columnMapping[self::MAPPING_LUA_UPDATER];
                    if (!array_key_exists($updaterCode, $this->luaUpdaters)) {
                        $this->luaUpdaters[$updaterCode] = $this->luaUpdaterRepository->findOneBy(['code' => $updaterCode]);
                    }

                    // Apply LUA script on value
                    if ($this->luaUpdaters[$updaterCode] !== null) {
                        $luaUpdater = $this->luaUpdaters[$updaterCode];
                        $lua        = new \Lua();
                        $lua->assign('attributeValue', $attributeValue);
                        $attributeValue = $lua->eval(sprintf(
                            "%s\n%s",
                            ProductAdvancedReader::LUA_SCRIPT_PREFIX,
                            $luaUpdater->getScript()
                        ));
                    } elseif (method_exists($this->exportHelper, $updaterCode)) {
                        $attributeValue = $this->exportHelper->{$updaterCode}($attributeValue, $item);
                    }
                }
                $newItem[$attributeCustomKey] = $attributeValue;
            }
        }

        // Complete item with export helper if necessary
        if (
            !empty($mapping[self::MAPPING_COMPLETE_CALLBACK_KEY])
            && method_exists($this->exportHelper, $mapping[self::MAPPING_COMPLETE_CALLBACK_KEY])
        ) {
            $newItem = $this->exportHelper->{$mapping[self::MAPPING_COMPLETE_CALLBACK_KEY]}($newItem, $item);
        }

        return $newItem;
    }

    /**
     * @param string $attributeValue
     * @param string $locale
     * @param string $customEntityClass
     *
     * @return string
     */
    protected function getReferenceValueFromCode($attributeValue, $locale, $customEntityClass)
    {
        $label      = '';
        $repository = $this->entityManager->getRepository($customEntityClass);

        /** @var AbstractTranslatableCustomEntity | AbstractCustomEntity $entity */
        $entity = $repository->findOneBy(array('code' => $attributeValue));
        if ($entity === null) {
            return $label;
        }

        // Check if we have translatable entity or not
        if ($entity instanceof AbstractTranslatableCustomEntity) {
            if (
                empty($entity->getLabels())
                || empty($entity->getLabels()[$locale])
            ) {
                return $label;
            }

            $label = $entity->getLabels()[$locale];
        } elseif ($entity instanceof AbstractCustomEntity) {
            $label = $entity->getLabel();
        }

        return $label;
    }

    /**
     * Get columns order by $mapping
     *
     * @param array $mapping
     *
     * @return array
     */
    protected function getColumnsOrderByMapping($mapping)
    {
        $columnsOrder = [];
        foreach ($mapping[self::MAPPING_COLUMNS_KEY] as $columnMapping) {
            if (
                isset($columnMapping[self::MAPPING_COLUMN_NAME_KEY])
                && !empty($columnMapping[self::MAPPING_COLUMN_NAME_KEY])
            ) {
                $columnsOrder[] = $columnMapping[self::MAPPING_COLUMN_NAME_KEY];
            } else {
                $columnsOrder[] = $columnMapping[self::MAPPING_ATTRIBUTE_CODE_KEY];
            }
        }

        return $columnsOrder;
    }
}
