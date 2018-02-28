<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Archiver;

use Akeneo\Component\Batch\Item\InvalidItemInterface;
use Akeneo\Component\Batch\Model\JobExecution;
use Doctrine\Common\Collections\ArrayCollection;
use Pim\Component\Connector\Archiver\CsvInvalidItemWriter;

/**
 * Extends default CsvInvalidItemWriter to disable creation of CSV file with errors for CSV import with mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Archiver
 */
class AdvancedCsvInvalidItemWriter extends CsvInvalidItemWriter
{
    /**
     * {@inheritdoc}
     *
     * Re-parse imported files and write into a new one the invalid lines.
     */
    public function archive(JobExecution $jobExecution)
    {
        if (empty($this->collector->getInvalidItems())) {
            return;
        }

        $invalidItemPositions = new ArrayCollection();
        foreach ($this->collector->getInvalidItems() as $invalidItem) {
            if ($invalidItem instanceof InvalidItemInterface) {
                $invalidItemPositions->add($invalidItem->getItemPosition());
            }
        }

        $readJobParameters = $jobExecution->getJobParameters();
        if ($readJobParameters->has('mapping')) {
            return;
        }
        $currentItemPosition = 0;
        $itemsToWrite        = [];

        $fileIterator = $this->getInputFileIterator($readJobParameters);
        $this->setupWriter($jobExecution);

        while ($fileIterator->valid()) {
            $readItem = $fileIterator->current();

            $currentItemPosition++;

            if ($invalidItemPositions->contains($currentItemPosition)) {
                $headers     = $fileIterator->getHeaders();
                $invalidItem = array_combine($headers, array_slice($readItem, 0, count($headers)));
                if (false !== $invalidItem) {
                    $itemsToWrite[] = $invalidItem;
                }

                $invalidItemPositions->removeElement($currentItemPosition);
            }

            if (count($itemsToWrite) > 0 && 0 === count($itemsToWrite) % $this->batchSize) {
                $this->writer->write($itemsToWrite);
                $itemsToWrite = [];
            }

            if ($invalidItemPositions->isEmpty()) {
                break;
            }

            $fileIterator->next();
        }

        if (count($itemsToWrite) > 0) {
            $this->writer->write($itemsToWrite);
        }

        $this->writer->flush();
    }
}
