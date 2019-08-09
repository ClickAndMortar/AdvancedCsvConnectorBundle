<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File\Csv;

use Akeneo\Pim\Structure\Component\Model\Attribute;
use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\Buffer\BufferFactory;
use ClickAndMortar\AdvancedCsvConnectorBundle\Doctrine\ORM\Repository\ExportMappingRepository;
use ClickAndMortar\AdvancedCsvConnectorBundle\Doctrine\ORM\Repository\LuaUpdaterRepository;
use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\ExportMapping;
use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\LuaUpdater;
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
     * Callback key in mapping
     *
     * @var string
     */
    const MAPPING_CALLBACK_KEY = 'callback';

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
     * Normalizer callback key in mapping
     *
     * @var string
     */
    const MAPPING_NORMALIZER_CALLBACK_KEY = 'normalizerCallback';

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
     * @param array                              $mediaAttributeTypes
     * @param String                             $jobParamFilePath
     * @param ExportHelper                       $exportHelper
     * @param EntityManager                      $entityManager
     * @param CustomEntityRepository             $exportMappingRepository
     * @param Container                          $container
     * @param CustomEntityRepository             $luaUpdaterRepository
     * @param string                             $defaultLocale
     */
    public function __construct(
        ArrayConverterInterface $arrayConverter,
        BufferFactory $bufferFactory,
        FlatItemBufferFlusher $flusher,
        AttributeRepositoryInterface $attributeRepository,
        FileExporterPathGeneratorInterface $fileExporterPath,
        array $mediaAttributeTypes,
        $jobParamFilePath = self::DEFAULT_FILE_PATH,
        ExportHelper $exportHelper,
        EntityManager $entityManager,
        CustomEntityRepository $exportMappingRepository,
        Container $container,
        CustomEntityRepository $luaUpdaterRepository,
        string $defaultLocale
    )
    {
        parent::__construct($arrayConverter, $bufferFactory, $flusher, $attributeRepository, $fileExporterPath, $mediaAttributeTypes, $jobParamFilePath);
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
    public function write(array $items)
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
     * {@inheritdoc}
     */
    protected function getWriterConfiguration()
    {
        $parameters = $this->stepExecution->getJobParameters();

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
    protected function getItemIdentifier(array $product)
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

        return $exportMapping->getMappingAsArray();
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
        if (isset($mapping[self::MAPPING_COLUMNS_KEY])) {
            $originalItem = $item;
            foreach ($item as $attributeKey => $attributeValue) {
                $keepCurrentAttribute = false;
                $attributeBaseValue   = $attributeValue;
                $locale               = $this->defaultLocale;
                foreach ($mapping[self::MAPPING_COLUMNS_KEY] as $columnMapping) {
                    $attributeValue = $attributeBaseValue;
                    if ($attributeKey == $columnMapping[self::MAPPING_ATTRIBUTE_CODE_KEY]) {
                        $keepCurrentAttribute = true;

                        // Force value if necessary
                        if (!empty($columnMapping[self::MAPPING_FORCE_VALUE_KEY])) {
                            $attributeValue      = $columnMapping[self::MAPPING_FORCE_VALUE_KEY];
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Normalize value if necessary
                        if (!empty($columnMapping[self::MAPPING_NORMALIZER_CALLBACK_KEY])) {
                            $normalizedValues    = $this->getNormalizedValuesByCode($mapping[self::MAPPING_NORMALIZERS_KEY], $columnMapping[self::MAPPING_NORMALIZER_CALLBACK_KEY]);
                            $attributeValue      = $this->getNormalizedValue($attributeValue, $normalizedValues);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Update value if necessary
                        if (!empty($columnMapping[self::MAPPING_CALLBACK_KEY]) && method_exists($this->exportHelper, $columnMapping[self::MAPPING_CALLBACK_KEY])) {
                            $attributeValue      = $this->exportHelper->{$columnMapping[self::MAPPING_CALLBACK_KEY]}($attributeValue, $originalItem);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Update value with LUA script if necessary
                        if (!empty($columnMapping[self::MAPPING_LUA_UPDATER])) {
                            // Get linked custom entity if necessary
                            $luaUpdaterCode = $columnMapping[self::MAPPING_LUA_UPDATER];
                            if (!array_key_exists($luaUpdaterCode, $this->luaUpdaters)) {
                                $this->luaUpdaters[$luaUpdaterCode] = $this->luaUpdaterRepository->findOneBy(['code' => $luaUpdaterCode]);
                            }

                            // Apply LUA script on value
                            if ($this->luaUpdaters[$luaUpdaterCode] !== null) {
                                $luaUpdater = $this->luaUpdaters[$luaUpdaterCode];
                                $lua        = new \Lua();
                                $lua->assign('attributeValue', $attributeValue);
                                $attributeValue      = $lua->eval($luaUpdater->getScript());
                                $item[$attributeKey] = $attributeValue;
                            }
                        }

                        // Set locale if necessary
                        if (!empty($columnMapping[self::MAPPING_LOCALE_KEY])) {
                            $locale = $columnMapping[self::MAPPING_LOCALE_KEY];
                        }

                        // Use Label instead of code in list value cases
                        if (!empty($columnMapping[self::MAPPING_USE_LABEL_KEY]) && $columnMapping[self::MAPPING_USE_LABEL_KEY] == true) {
                            $attributeValue      = $this->exportHelper->getValueFromCode($attributeKey, $attributeValue, $locale);
                            $item[$attributeKey] = $attributeValue;

                            // Load attribute to check if we have custom entity linked
                            if (!array_key_exists($attributeKey, $this->attributes)) {
                                $this->attributes[$attributeKey] = $this->attributeRepository->findOneByIdentifier($attributeKey);
                            }
                            if (!empty($this->attributes[$attributeKey]) && !empty($this->attributes[$attributeKey]->getReferenceDataName())) {
                                $customEntityClassParameter = sprintf('pim_custom_entity.entity.%s.class', $this->attributes[$attributeKey]->getReferenceDataName());
                                if ($this->container->hasParameter($customEntityClassParameter)) {
                                    $customEntityClass   = $this->container->getParameter($customEntityClassParameter);
                                    $attributeValue      = $this->getReferenceValueFromCode($attributeBaseValue, $locale, $customEntityClass);
                                    $item[$attributeKey] = $attributeValue;
                                }
                            }
                        }

                        // Capitalize value if necessary
                        if (!empty($columnMapping[self::MAPPING_CAPITALIZED_KEY]) && $columnMapping[self::MAPPING_CAPITALIZED_KEY] == true) {
                            $attributeValue      = strtoupper($item[$attributeKey]);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Shorten value if necessary
                        if (!empty($columnMapping[self::MAPPING_MAX_LENGTH_KEY]) && strlen($attributeValue) > $columnMapping[self::MAPPING_MAX_LENGTH_KEY]) {
                            $attributeValue = substr($attributeValue, 0, $columnMapping[self::MAPPING_MAX_LENGTH_KEY]);
                        }

                        // Set default value if necessary
                        if (!empty($columnMapping[self::MAPPING_DEFAULT_VALUE_KEY]) && empty($attributeValue)) {
                            $attributeValue = $columnMapping[self::MAPPING_DEFAULT_VALUE_KEY];
                        }

                        // Update column name if necessary
                        if (!empty($columnMapping[self::MAPPING_COLUMN_NAME_KEY])) {
                            $keepCurrentAttribute                                = false;
                            $item[$columnMapping[self::MAPPING_COLUMN_NAME_KEY]] = $attributeValue;
                        }

                        if (!empty($columnMapping[self::MAPPING_LOCALE_KEY])
                            && !in_array($columnMapping[self::MAPPING_LOCALE_KEY], $localesToExport)
                        ) {
                            unset($item[$columnMapping[self::MAPPING_COLUMN_NAME_KEY]]);
                        }
                    }

                    if ($attributeKey != $columnMapping[self::MAPPING_ATTRIBUTE_CODE_KEY]
                        && !empty($columnMapping[self::MAPPING_COLUMN_NAME_KEY])
                        && $attributeKey == $columnMapping[self::MAPPING_COLUMN_NAME_KEY]
                    ) {
                        $keepCurrentAttribute = true;
                    }
                }

                // Delete original column
                if (!$keepCurrentAttribute) {
                    unset($item[$attributeKey]);
                }
            }
        }

        // Complete item with export helper if necessary
        if (
            !empty($mapping[self::MAPPING_COMPLETE_CALLBACK_KEY])
            && method_exists($this->exportHelper, $mapping[self::MAPPING_COMPLETE_CALLBACK_KEY])
        ) {
            $item = $this->exportHelper->{$mapping[self::MAPPING_COMPLETE_CALLBACK_KEY]}($item);
        }

        return $item;
    }

    /**
     * Get normalized values by normalizer code
     *
     * @param array  $normalizers
     * @param string $normalizerCode
     *
     * @return array
     */
    protected function getNormalizedValuesByCode($normalizers, $normalizerCode)
    {
        foreach ($normalizers as $normalizer) {
            if (
                isset($normalizer['code'])
                && isset($normalizer['values'])
                && $normalizer['code'] === $normalizerCode
            ) {
                return $normalizer['values'];
            }
        }

        return [];
    }


    /**
     * Get normalized value from values
     *
     * @param string $value
     * @param array  $normalizedValues
     *
     * @return string
     */
    protected function getNormalizedValue($value, $normalizedValues)
    {
        foreach ($normalizedValues as $normalizedValue) {
            if (in_array($value, $normalizedValue['originalValues'])) {
                return $normalizedValue['normalizedValue'];
            }
        }

        return $value;
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
}
