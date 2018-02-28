<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Archiver;

use Akeneo\Component\Batch\Model\JobExecution;
use Akeneo\Component\Batch\Step\ItemStep;
use ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv\ProductAdvancedReader;
use Pim\Component\Connector\Archiver\FileReaderArchiver;

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
            if (!$step instanceof ItemStep) {
                continue;
            }
            $reader = $step->getReader();

            if ($this->isReaderUsable($reader)) {
                if ($reader instanceof ProductAdvancedReader) {
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
                } else {
                    $jobParameters = $jobExecution->getJobParameters();
                    $filePath      = $jobParameters->get('filePath');
                    $key           = strtr(
                        $this->getRelativeArchivePath($jobExecution),
                        [
                            '%filename%' => basename($filePath),
                        ]
                    );
                    $this->filesystem->put($key, file_get_contents($filePath));
                }
            }
        }
    }
}
