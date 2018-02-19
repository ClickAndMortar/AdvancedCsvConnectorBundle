<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Helper;

use Exception;
use Monolog\Logger;
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
     * ImportHelper constructor.
     *
     * @param Logger $logger
     * @param string $kernelRootDirectory
     * @param string $dataInDirectory
     */
    public function __construct(Logger $logger, $kernelRootDirectory, $dataInDirectory)
    {
        $this->logger              = $logger;
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
        $filePaths                 = array();
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
