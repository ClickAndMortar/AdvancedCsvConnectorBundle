<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Helper;

use Akeneo\Platform\Bundle\UIBundle\Translator\TranslatorDecorator;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Model\Warning;
use Doctrine\ORM\EntityManager;
use Exception;
use Monolog\Logger;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Component\Model\Attribute;
use Akeneo\Pim\Structure\Component\AttributeTypes;
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
     * Product visual prefix for URL download
     *
     * @var string
     */
    const PRODUCT_VISUAL_PREFIX = 'downloaded-visual-';

    /**
     * exif_imagetype throws "Read error!" if file is too small (for image download)
     *
     * @var int
     */
    const EXIF_IMAGETYPE_FILE_MIN_SIZE = 12;

    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Entity manager
     *
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * Kernel root directory
     *
     * @var string
     */
    protected $kernelRootDirectory;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var TranslatorDecorator
     */
    protected $translator;

    /**
     * @var StepExecution
     */
    protected $stepExecution;

    /**
     * Valid HTTP codes for file download
     *
     * @var array
     */
    protected $downloadValidCodes = [
        '200',
        '304',
    ];

    /**
     * ImportHelper constructor.
     *
     * @param Logger              $logger
     * @param EntityManager       $entityManager
     * @param string              $kernelRootDirectory
     * @param AttributeRepository $attributeRepository
     * @param TranslatorDecorator $translator
     */
    public function __construct(
        Logger $logger,
        EntityManager $entityManager,
        $kernelRootDirectory,
        AttributeRepository $attributeRepository,
        TranslatorDecorator $translator
    )
    {
        $this->logger              = $logger;
        $this->entityManager       = $entityManager;
        $this->kernelRootDirectory = $kernelRootDirectory;
        $this->attributeRepository = $attributeRepository;
        $this->translator          = $translator;
    }

    /**
     * Set step execution
     *
     * @param StepExecution $stepExecution
     *
     * @return void
     */
    public function setStepExecution($stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * Split CSV files if necessary and return paths
     *
     * @param string $filePath
     * @param string $fromEncoding
     *
     * @return array
     */
    public function splitFiles($filePath, $fromEncoding = '')
    {
        $filePaths                 = [];
        $hasAtLeastOneSplittedFile = false;
        $prefixFilename            = str_replace('*', '', pathinfo($filePath, PATHINFO_FILENAME));
        $prefixFilename            = str_replace(' ', '-', $prefixFilename);
        $currentFilePaths          = glob($filePath);
        $directoryPath             = pathinfo($filePath, PATHINFO_DIRNAME);

        foreach ($currentFilePaths as $index => $currentFilePath) {
            // Remove spaces from filename
            if (strpos($currentFilePath, ' ') !== false) {
                $newFilePath = str_replace(' ', '-', $currentFilePath);
                rename($currentFilePath, $newFilePath);
                $currentFilePath          = $newFilePath;
                $currentFilePaths[$index] = $currentFilePath;
            }

            // Encode file if necessary
            $this->encodeToUtf8($currentFilePath, $fromEncoding);

            $linesNumber = $this->getLinesNumberByFilePath($currentFilePath);
            if ($linesNumber > self::MAX_LINES_PER_FILE + 2) {
                $hasAtLeastOneSplittedFile = true;
                $bashCommand               = sprintf(
                    'sh %s/vendor/clickandmortar/advanced-csv-connector-bundle/Resources/bin/split-csv-files.sh --file_path=%s --lines_per_file=%s --target_folder=%s --prefix=%s',
                    $this->kernelRootDirectory,
                    $currentFilePath,
                    self::MAX_LINES_PER_FILE,
                    $directoryPath,
                    $prefixFilename . $index . '_'
                );
                $process                   = Process::fromShellCommandline($bashCommand);
                $process->mustRun();
            }
        }

        if ($hasAtLeastOneSplittedFile) {
            $filePaths = glob(sprintf(
                '%s/%s*.csv',
                $directoryPath,
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
        $attributeValue = str_replace(',', '.', $attributeValue);
        $attributeValue = !empty($attributeValue) ? floatval($attributeValue) : 0;

        /** @var Attribute $attribute */
        $attribute = $this->attributeRepository->findOneByIdentifier($attributeCode);
        if ($attribute !== null && $attribute->getBackendType() == AttributeTypes::BACKEND_TYPE_METRIC) {
            $attributeValue = sprintf('%s %s', $attributeValue, $attribute->getDefaultMetricUnit());
        }

        return $attributeValue;
    }

    /**
     * Download visual from URL and return path
     *
     * @param string $attributeValue
     *
     * @return null|string
     * @throws Exception
     */
    public function downloadVisualFromUrl($attributeValue)
    {
        $visualPath = null;
        if (empty($attributeValue)) {
            return $visualPath;
        }

        try {
            if (!$this->canBeDownloaded($attributeValue)) {
                return null;
            }

            // Check for upload directory
            $uploadDirectory = sprintf('%s/var/file_storage/downloaded_visuals', $this->kernelRootDirectory);
            if (!is_dir($uploadDirectory)) {
                if (!mkdir($uploadDirectory, 0774, true)) {
                    return $visualPath;
                }
            }

            $extension  = pathinfo($attributeValue, PATHINFO_EXTENSION);
            $visualPath = sprintf(
                '%s/%s.%s',
                $uploadDirectory,
                uniqid(self::PRODUCT_VISUAL_PREFIX),
                $extension
            );
            file_put_contents($visualPath, file_get_contents($attributeValue));

            // Check visual
            if (filesize($visualPath) < self::EXIF_IMAGETYPE_FILE_MIN_SIZE || exif_imagetype($visualPath) === false) {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }

        return $visualPath;
    }

    /**
     * Check if file with $url can be downloaded
     *
     * @param string $url
     *
     * @return bool
     */
    public function canBeDownloaded($url)
    {
        $curlRequest = curl_init($url);
        curl_setopt($curlRequest, CURLOPT_HEADER, true);
        curl_setopt($curlRequest, CURLOPT_NOBODY, true);
        curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlRequest, CURLOPT_TIMEOUT, 5);
        curl_exec($curlRequest);
        $responseCode = curl_getinfo($curlRequest, CURLINFO_HTTP_CODE);
        curl_close($curlRequest);

        return in_array($responseCode, $this->downloadValidCodes);
    }

    /**
     * Replace comma by dot
     *
     * @param string $attributeValue
     * @param string $attributeCode
     *
     * @return string
     */
    public function replaceCommaByDot($attributeValue, $attributeCode)
    {
        return str_replace(',', '.', $attributeValue);
    }

    /**
     * Check and encode file to UTF-8 if necessary
     *
     * @param string $filePath
     * @param string $fromEncoding
     *
     * @return void
     */
    protected function encodeToUtf8($filePath, $fromEncoding)
    {
        try {
            if (empty($fromEncoding)) {
                $checkEncodingCommand = sprintf('file -i %s | cut -f 2 -d";" | cut -f 2 -d=', $filePath);
                $checkEncodingProcess = Process::fromShellCommandline($checkEncodingCommand);
                $checkEncodingProcess->mustRun();
                $currentEncoding = $checkEncodingProcess->getOutput();
                $currentEncoding = str_replace("\n", "", $currentEncoding);
            } else {
                $currentEncoding = $fromEncoding;
            }

            // Convert to UTF-8 if necessary with iconv linx command
            if (!empty($currentEncoding) && strpos($currentEncoding, self::FILE_ENCODING_UTF8) == false) {
                $newFilePath       = sprintf('%s.temp', $filePath);
                $encodeFileCommand = sprintf(
                    'iconv -f %s -t UTF-8 %s > %s',
                    $currentEncoding,
                    $filePath,
                    $newFilePath
                );
                $encodeFileProcess = Process::fromShellCommandline($encodeFileCommand);
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

    /**
     * Add warning message to current step execution
     *
     * @param string $message
     * @param array  $messagesParameters
     *
     * @return void
     */
    protected function addWarning($message, $messagesParameters = [])
    {
        $warning = new Warning(
            $this->stepExecution,
            $this->translator->trans($message, $messagesParameters),
            [],
            []
        );
        $this->jobRepository->addWarning($warning);
    }
}
