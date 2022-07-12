<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Job\JobParameters\DefaultValuesProvider;

use Akeneo\Component\Batch\Job\JobInterface;
use Akeneo\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface;

/**
 * Default values provider for product CSV advanced import
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package Solaris\Bundle\RestConnectorBundle\Job\JobParameters\DefaultValuesProvider
 */
class ProductCsvAdvancedImport implements DefaultValuesProviderInterface
{
    /**
     * Default mapping value
     *
     * @var string
     */
    const DEFAULT_MAPPING = '{\'your-json-mapping-key\': \'your-json-mapping-value\'}';

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
            'mapping'      => self::DEFAULT_MAPPING,
            'fromEncoding' => ''
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
