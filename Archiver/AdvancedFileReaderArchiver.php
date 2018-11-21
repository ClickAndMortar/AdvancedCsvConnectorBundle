<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Archiver;

use Akeneo\Component\Batch\Model\JobExecution;
use Akeneo\Component\Batch\Step\ItemStep;
use Pim\Component\Connector\Archiver\FileReaderArchiver;
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
    public function archive(JobExecution $jobExecution)
    {
        $job = $this->jobRegistry->get($jobExecution->getJobInstance()->getJobName());
        foreach ($job->getSteps() as $step) {
            $reader    = $step->getReader();
            $filePaths = $reader->getFilePaths();
            foreach ($filePaths as $filePath) {
                if (file_exists($filePath)) {
                    $key = strtr(
                        $this->getRelativeArchivePath($jobExecution),
                        [
                            '%filename%' => basename($filePath),
                        ]
                    );
                    $this->filesystem->put($key, file_get_contents($filePath));
                    unlink($filePath);
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
    public function supports(JobExecution $jobExecution)
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
