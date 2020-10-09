<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Job\JobParameters\ConstraintCollectionProvider;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Default value provider for product CSV advanced import
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Job\JobParameters\ConstraintCollectionProvider
 */
class ProductCsvAdvancedImport implements ConstraintCollectionProviderInterface
{
    /**
     * @var ConstraintCollectionProviderInterface
     */
    protected $baseConstraintCollectionProvider;

    /**
     * @var array
     */
    protected $supportedJobNames;

    /**
     * @param ConstraintCollectionProviderInterface $baseConstraintCollectionProvider
     * @param array                                 $supportedJobNames
     */
    public function __construct(ConstraintCollectionProviderInterface $baseConstraintCollectionProvider, array $supportedJobNames)
    {
        $this->baseConstraintCollectionProvider = $baseConstraintCollectionProvider;
        $this->supportedJobNames                = $supportedJobNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection()
    {
        $constraintFields = array_merge($this->baseConstraintCollectionProvider->getConstraintCollection()->fields, [
            'mapping'         => [
                new NotBlank(),
            ],
            'emailRecipients' => [],
        ]);

        return new Collection(['fields' => $constraintFields]);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job)
    {
        return in_array($job->getName(), $this->supportedJobNames);
    }
}
