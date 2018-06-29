<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Writer\File\Csv;

use Akeneo\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Component\Batch\Job\JobInterface;
use Akeneo\Component\Batch\Job\JobParameters;
use Akeneo\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Component\Buffer\BufferFactory;
use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;
use Pim\Bundle\CustomEntityBundle\Entity\AbstractTranslatableCustomEntity;
use Pim\Component\Catalog\Repository\AttributeRepositoryInterface;
use Pim\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Pim\Component\Connector\Writer\File\AbstractItemMediaWriter;
use Pim\Component\Connector\Writer\File\ArchivableWriterInterface;
use Pim\Component\Connector\Writer\File\FileExporterPathGeneratorInterface;
use Pim\Component\Connector\Writer\File\FlatItemBufferFlusher;
use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use ClickAndMortar\AdvancedCsvConnectorBundle\Helper\ExportHelper;
use Doctrine\ORM\EntityManager;

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
     * Additional columns key in mapping
     *
     * @var string
     */
    const MAPPING_ADDITIONAL_COLUMNS_KEY = 'additionalColumns';

    /**
     * Column name key in mapping
     *
     * @var string
     */
    const MAPPING_COLUMN_NAME_KEY = 'columnName';

    /**
     * Replacements key in mapping
     *
     * @var string
     */
    const MAPPING_REPLACEMENTS_KEY = 'replacements';

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
     * Additional headers line key
     *
     * @var string
     */
    const MAPPING_ADDITIONAL_HEADERS_LINE_KEY = 'additionalHeadersLine';

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
     * Default locale
     *
     * @var string
     */
    protected $defaultLocale;

    /**
     * Additional headers line (optional) added?
     *
     * @var bool
     */
    protected $additionalHeadersLineAdded = false;

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
     * @param string                             $defaultLocale
     */
    public function __construct(
        ArrayConverterInterface $arrayConverter,
        BufferFactory $bufferFactory,
        FlatItemBufferFlusher $flusher,
        AttributeRepositoryInterface $attributeRepository,
        FileExporterPathGeneratorInterface $fileExporterPath,
        array $mediaAttributeTypes,
        string $jobParamFilePath = self::DEFAULT_FILE_PATH,
        ExportHelper $exportHelper,
        EntityManager $entityManager,
        string $defaultLocale
    )
    {
        parent::__construct($arrayConverter, $bufferFactory, $flusher, $attributeRepository, $fileExporterPath, $mediaAttributeTypes, $jobParamFilePath);
        $this->exportHelper  = $exportHelper;
        $this->entityManager = $entityManager;
        $this->defaultLocale = $defaultLocale;
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

        // Check for additional headers line
        if (isset($mapping[self::MAPPING_ADDITIONAL_HEADERS_LINE_KEY]) && !$this->additionalHeadersLineAdded) {
            $flatItems[]                      = $mapping[self::MAPPING_ADDITIONAL_HEADERS_LINE_KEY];
            $this->additionalHeadersLineAdded = true;
        }

        foreach ($items as $item) {
            if ($parameters->has('with_media') && $parameters->get('with_media')) {
                $item = $this->resolveMediaPaths($item, $directory);
            }
            $flatItem    = $this->arrayConverter->convert($item, $converterOptions);
            $flatItem    = $this->updateItemByMapping($flatItem, $mapping);
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
        $mappingAsArray = [];
        $mapping        = $parameters->get('mapping');
        $mapping        = json_decode($mapping, true);
        if (is_array($mapping)) {
            $mappingAsArray = $mapping;
        }

        return $mappingAsArray;
    }

    /**
     * Update item keys and values with $mapping from export job
     *
     * @param array $item
     * @param array $mapping
     *
     * @return array
     */
    protected function updateItemByMapping(array $item, array $mapping)
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
                        if (isset($columnMapping[self::MAPPING_FORCE_VALUE_KEY])) {
                            $attributeValue      = $columnMapping[self::MAPPING_FORCE_VALUE_KEY];
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Normalize value if necessary
                        if (isset($columnMapping[self::MAPPING_NORMALIZER_CALLBACK_KEY])) {
                            $normalizedValues    = $this->getNormalizedValuesByCode($mapping[self::MAPPING_NORMALIZERS_KEY], $columnMapping[self::MAPPING_NORMALIZER_CALLBACK_KEY]);
                            $attributeValue      = $this->getNormalizedValue($attributeValue, $normalizedValues);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Update value if necessary
                        if (isset($columnMapping[self::MAPPING_CALLBACK_KEY]) && method_exists($this->exportHelper, $columnMapping[self::MAPPING_CALLBACK_KEY])) {
                            $attributeValue      = $this->exportHelper->{$columnMapping[self::MAPPING_CALLBACK_KEY]}($attributeValue, $originalItem);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Replace specific characters if necessary
                        if (isset($mapping[self::MAPPING_REPLACEMENTS_KEY])) {
                            foreach ($mapping[self::MAPPING_REPLACEMENTS_KEY] as $replacement) {
                                $attributeValue      = str_replace($replacement['values'], $replacement['newValue'], $attributeValue);
                                $item[$attributeKey] = $attributeValue;
                            }
                        }

                        // Set locale if necessary
                        if (isset($columnMapping[self::MAPPING_LOCALE_KEY])) {
                            $locale = $columnMapping[self::MAPPING_LOCALE_KEY];
                        }

                        // Use Label instead of code in list value cases
                        if (isset($columnMapping[self::MAPPING_USE_LABEL_KEY]) && $columnMapping[self::MAPPING_USE_LABEL_KEY] == true) {
                            $attributeValue      = $this->exportHelper->getValueFromCode($attributeKey, $attributeValue, $locale);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Use reference label instead of code in reference data cases
                        if (isset($columnMapping[self::MAPPING_USE_REFERENCE_LABEL_KEY])) {
                            $attributeValue      = $this->getReferenceValueFromCode($attributeValue, $locale, $columnMapping[self::MAPPING_USE_REFERENCE_LABEL_KEY]);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Capitalize value if necessary
                        if (isset($columnMapping[self::MAPPING_CAPITALIZED_KEY]) && $columnMapping[self::MAPPING_CAPITALIZED_KEY] == true) {
                            $attributeValue      = strtoupper($item[$attributeKey]);
                            $item[$attributeKey] = $attributeValue;
                        }

                        // Shorten value if necessary
                        if (isset($columnMapping[self::MAPPING_MAX_LENGTH_KEY]) && strlen($attributeValue) > $columnMapping[self::MAPPING_MAX_LENGTH_KEY]) {
                            $attributeValue = substr($attributeValue, 0, $columnMapping[self::MAPPING_MAX_LENGTH_KEY]);
                        }

                        // Update column name if necessary
                        if (isset($columnMapping[self::MAPPING_COLUMN_NAME_KEY])) {
                            $keepCurrentAttribute                                = false;
                            $item[$columnMapping[self::MAPPING_COLUMN_NAME_KEY]] = $attributeValue;
                        }
                    }

                    if ($attributeKey != $columnMapping[self::MAPPING_ATTRIBUTE_CODE_KEY]
                        && isset($columnMapping[self::MAPPING_COLUMN_NAME_KEY])
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

        // Add additional columns
        if (isset($mapping[self::MAPPING_ADDITIONAL_COLUMNS_KEY])) {
            foreach ($mapping[self::MAPPING_ADDITIONAL_COLUMNS_KEY] as $additionalColumn) {
                $item[$additionalColumn[self::MAPPING_COLUMN_NAME_KEY]] = $additionalColumn['value'];
            }
        }

        // Complete item with export helper if necessary
        if (
            isset($mapping[self::MAPPING_COMPLETE_CALLBACK_KEY])
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
     * @param string $entity
     *
     * @return string
     */
    protected function getReferenceValueFromCode($attributeValue, $locale, $entity)
    {
        $label      = '';
        $repository = $this->entityManager->getRepository($entity);

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
