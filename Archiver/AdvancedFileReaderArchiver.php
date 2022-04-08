<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Archiver;

use Akeneo\Tool\Component\Batch\Model\JobExecution;
use Akeneo\Tool\Component\Batch\Step\ItemStep;
use Akeneo\Tool\Component\Connector\Archiver\FileReaderArchiver;
use ClickAndMortar\AdvancedCsvConnectorBundle\Reader\MultiFilesReaderInterface;

/**
 * Advanced file reader archiver
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package Pim\Component\Connector\Archiver
 */
class AdvancedFileReaderArchiver extends FileReaderArchiver
{
    /**
     * Archive files used by job execution (input / output)
     *
     * @param JobExecution $jobExecution
     */
    public function archive(JobExecution $jobExecution): void
    {
        $job = $this->jobRegistry->get($jobExecution->getJobInstance()->getJobName());
        foreach ($job->getSteps() as $step) {
            if (
                method_exists($step, 'getReader')
                && $step->getReader() instanceof MultiFilesReaderInterface
            ) {
                $reader    = $step->getReader();
                $filePaths = $reader->getFilePaths();
                foreach ($filePaths as $filePath) {
                    if (file_exists($filePath)) {
                        $archivePath = strtr(
                            $this->getRelativeArchivePath($jobExecution),
                            [
                                '%filename%' => basename($filePath),
                            ]
                        );

                        if (is_readable($filePath)) {
                            $fileResource = fopen($filePath, 'r');
                            $this->filesystem->writeStream($archivePath, $fileResource);

                            if (is_resource($fileResource)) {
                                fclose($fileResource);
                            }
                        }
                        unlink($filePath);
                    }
                }
            }
        }
    }

    /**
     * Check if the job execution is supported
     *
     * @param JobExecution $jobExecution
     *
     * @return bool
     */
    public function supports(JobExecution $jobExecution): bool
    {
        $job = $this->jobRegistry->get($jobExecution->getJobInstance()->getJobName());
        foreach ($job->getSteps() as $step) {
            if (
                method_exists($step, 'getReader')
                && $step->getReader() instanceof MultiFilesReaderInterface
            ) {
                return true;
            }
        }

        return false;
    }
}
