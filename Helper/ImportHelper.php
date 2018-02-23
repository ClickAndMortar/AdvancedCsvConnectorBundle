<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Helper;

use Exception;
use Monolog\Logger;
use Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository\AttributeRepository;
use Pim\Bundle\CatalogBundle\Entity\Attribute;
use Pim\Component\Catalog\AttributeTypes;
use Symfony\Component\Process\Process;

/**
 * Import helper
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Helper
 */
class ImportHelper
{
    /**
     * UTF-8 file encoding
     *
     * @var string
     */
    const FILE_ENCODING_UTF8 = 'utf-8';

    /**
     * Max lines number per file (split if necessary)
     *
     * @var int
     */
    const MAX_LINES_PER_FILE = 1000;

    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Kernel root directory
     *
     * @var string
     */
    protected $kernelRootDirectory;

    /**
     * Data in directory
     *
     * @var string
     */
    protected $dataInDirectory;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * ImportHelper constructor.
     *
     * @param Logger              $logger
     * @param AttributeRepository $attributeRepository
     * @param string              $kernelRootDirectory
     * @param string              $dataInDirectory
     */
    public function __construct(Logger $logger, AttributeRepository $attributeRepository, $kernelRootDirectory, $dataInDirectory)
    {
        $this->logger              = $logger;
        $this->attributeRepository = $attributeRepository;
        $this->kernelRootDirectory = $kernelRootDirectory;
        $this->dataInDirectory     = $dataInDirectory;
    }

    /**
     * Split CSV files if necessary and return paths
     *
     * @param string $filePath
     *
     * @return array
     */
    public function splitFiles($filePath)
    {
        $filePaths                 = [];
        $hasAtLeastOneSplittedFile = false;
        $prefixFilename            = str_replace('*', '', pathinfo($filePath, PATHINFO_FILENAME));
        $prefixFilename            = str_replace(' ', '-', $prefixFilename);
        $currentFilePaths          = glob($filePath);

        foreach ($currentFilePaths as $index => $currentFilePath) {
            // Remove spaces from filename
            if (strpos($currentFilePath, ' ') !== false) {
                $newFilePath = str_replace(' ', '-', $currentFilePath);
                rename($currentFilePath, $newFilePath);
                $currentFilePath          = $newFilePath;
                $currentFilePaths[$index] = $currentFilePath;
            }

            // Encode file if necessary
            $this->encodeToUtf8($currentFilePath);

            $linesNumber = $this->getLinesNumberByFilePath($currentFilePath);
            if ($linesNumber > self::MAX_LINES_PER_FILE) {
                $hasAtLeastOneSplittedFile = true;
                $bashCommand               = sprintf(
                    'sh %s/../vendor/clickandmortar/advanced-csv-connector-bundle/Resources/bin/split-csv-files.sh --file_path=%s --lines_per_file=%s --target_folder=%s --prefix=%s',
                    $this->kernelRootDirectory,
                    $currentFilePath,
                    self::MAX_LINES_PER_FILE,
                    $this->dataInDirectory,
                    $prefixFilename . $index . '_'
                );
                $process                   = new Process($bashCommand);
                $process->mustRun();
            }
        }

        if ($hasAtLeastOneSplittedFile) {
            $filePaths = glob(sprintf(
                '%s/%s*.csv',
                $this->dataInDirectory,
                $prefixFilename
            ));
        } else {
            if (is_array($currentFilePaths) && !empty($currentFilePaths)) {
                $filePaths = $currentFilePaths;
            }
        }

        return $filePaths;
    }

    /**
     * Get value in $item by $code
     *
     * @param array  $item
     * @param string $code
     * @param null   $default
     *
     * @return string | null
     */
    public function getByCode($item, $code, $default = null)
    {
        if (array_key_exists($code, $item)) {
            return $item[$code];
        }

        return $default;
    }

    /**
     * Complete metric attribute value with unit
     *
     * @param string $attributeValue
     * @param string $attributeCode
     *
     * @return string
     */
    public function setMetricUnitAsSuffix($attributeValue, $attributeCode)
    {
        // Set to 0 if we have empty value
        $attributeValue = !empty($attributeValue) ? floatval($attributeValue) : 0;

        /** @var Attribute $attribute */
        $attribute = $this->attributeRepository->findOneByIdentifier($attributeCode);
        if ($attribute !== null && $attribute->getBackendType() == AttributeTypes::BACKEND_TYPE_METRIC) {
            $attributeValue = sprintf('%s %s', $attributeValue, $attribute->getDefaultMetricUnit());
        }

        return $attributeValue;
    }

    /**
     * Get normalized value
     *
     * @param string $value
     * @param array  $normalizedValuesArray
     *
     * @return string
     */
    public function getNormalizedValue($value, $normalizedValuesArray)
    {
        foreach ($normalizedValuesArray as $normalizedValueArray) {
            if (
                isset($normalizedValueArray['originalValues'])
                && isset($normalizedValueArray['normalizedValue'])
                && in_array($value, $normalizedValueArray['originalValues'])
            ) {
                return $normalizedValueArray['normalizedValue'];
            }
        }

        return $value;
    }

    /**
     * Check and encode file to UTF-8 if necessary
     *
     * @param string $filePath
     *
     * @return void
     */
    protected function encodeToUtf8($filePath)
    {
        try {
            $checkEncodingCommand = sprintf('file -i %s | cut -f 2 -d";" | cut -f 2 -d=', $filePath);
            $checkEncodingProcess = new Process($checkEncodingCommand);
            $checkEncodingProcess->mustRun();
            $currentEncoding = $checkEncodingProcess->getOutput();
            $currentEncoding = str_replace("\n", "", $currentEncoding);

            // Convert to UTF-8 if necessary with iconv linx command
            if (strpos($currentEncoding, self::FILE_ENCODING_UTF8) == false) {
                $newFilePath       = sprintf('%s.temp', $filePath);
                $encodeFileCommand = sprintf(
                    'iconv -f %s -t UTF-8 %s > %s',
                    $currentEncoding,
                    $filePath,
                    $newFilePath
                );
                $encodeFileProcess = new Process($encodeFileCommand);
                $encodeFileProcess->mustRun();

                // Replace bad encoding file with correct one
                if (file_exists($newFilePath)) {
                    unlink($filePath);
                    rename($newFilePath, $filePath);
                }
            }
        } catch (Exception $e) {
            $errorMessage = sprintf('Error during encoding update of CSV file %s', $filePath);
            $this->logger->error($errorMessage);
        }
    }

    /**
     * Return lines number for a file (avoiding memory issues)
     *
     * @param string $filePath
     *
     * @return int
     */
    protected function getLinesNumberByFilePath($filePath)
    {
        $linesNumber = 0;

        $file = fopen($filePath, 'r');
        while (!feof($file)) {
            fgets($file);
            $linesNumber++;
        }
        fclose($file);

        return $linesNumber;
    }
}
