<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Job\JobParameters\DefaultValuesProvider;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface;

/**
 * Default values provider for product CSV export
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Job\JobParameters\DefaultValuesProvider
 */
class ProductCsvAdvancedExport implements DefaultValuesProviderInterface
{
    /**
     * @var DefaultValuesProviderInterface
     */
    protected $baseDefaultValuesProvider;

    /**
     * @var array
     */
    protected $supportedJobNames;

    /**
     * @param DefaultValuesProviderInterface $baseDefaultValuesProvider
     * @param array                          $supportedJobNames
     */
    public function __construct(DefaultValuesProviderInterface $baseDefaultValuesProvider, array $supportedJobNames)
    {
        $this->baseDefaultValuesProvider = $baseDefaultValuesProvider;
        $this->supportedJobNames         = $supportedJobNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValues()
    {
        return array_merge($this->baseDefaultValuesProvider->getDefaultValues(), [
            'mapping' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job)
    {
        return in_array($job->getName(), $this->supportedJobNames);
    }
}
