<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Processor\Normalization;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\CacheClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\ValuesFiller\EntityWithFamilyValuesFillerInterface;
use Akeneo\Tool\Component\Connector\Processor\BulkMediaFetcher;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Normalization\ProductProcessor;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Akeneo\Channel\Component\Repository\ChannelRepositoryInterface;

/**
 * Extension of classic ProductProcessor to allow write file urls as serialized product data
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Processor\Normalization
 */
class AdvancedProductProcessor extends ProductProcessor
{
    /**
     * Media url prefix
     *
     * @var string
     */
    protected $mediaUrlPrefix;

    /**
     * AdvancedProductProcessor constructor.
     *
     * @param NormalizerInterface                   $normalizer
     * @param IdentifiableObjectRepositoryInterface $channelRepository
     * @param AttributeRepositoryInterface          $attributeRepository
     * @param BulkMediaFetcher                      $mediaFetcher
     * @param EntityWithFamilyValuesFillerInterface $productValuesFiller
     * @param string                                $mediaUrlPrefix
     */
    public function __construct(
        NormalizerInterface $normalizer,
        IdentifiableObjectRepositoryInterface $channelRepository,
        AttributeRepositoryInterface $attributeRepository,
        BulkMediaFetcher $mediaFetcher,
        EntityWithFamilyValuesFillerInterface $productValuesFiller,
        $mediaUrlPrefix
    )
    {
        parent::__construct($normalizer, $channelRepository, $attributeRepository, $mediaFetcher, $productValuesFiller);
        $this->mediaUrlPrefix = $mediaUrlPrefix;
    }

    /**
     * Override parent method to add media url instead of native delete from normalized product
     *
     * {@inheritdoc}
     */
    public function process($product)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $structure  = $parameters->get('filters')['structure'];
        $channel    = $this->channelRepository->findOneByIdentifier($structure['scope']);
        $this->productValuesFiller->fillMissingValues($product);

        $productStandard = $this->normalizer->normalize($product, 'standard', [
            'channels' => [$channel->getCode()],
            'locales'  => array_intersect(
                $channel->getLocaleCodes(),
                $parameters->get('filters')['structure']['locales']
            ),
        ]);

        if ($this->areAttributesToFilter($parameters)) {
            $attributesToFilter        = $this->getAttributesToFilter($parameters);
            $productStandard['values'] = $this->filterValues($productStandard['values'], $attributesToFilter);
        }

        if ($parameters->has('with_media') && $parameters->get('with_media')) {
            $directory = $this->stepExecution->getJobExecution()->getExecutionContext()
                                             ->get(JobInterface::WORKING_DIRECTORY_PARAMETER);

            $this->fetchMedia($product, $directory);
        } else {
            $mediaAttributes = $this->attributeRepository->findMediaAttributeCodes();
            foreach ($mediaAttributes as $mediaAttribute) {
                if (isset($productStandard['values'][$mediaAttribute])) {
                    foreach ($productStandard['values'][$mediaAttribute] as $index => $mediaValue) {
                        if (isset($mediaValue['data']) && !empty($mediaValue)) {
                            // Replace local path by url
                            $mediaUrl                                                   = $this->getUrlByLocalPath($mediaValue['data']);
                            $productStandard['values'][$mediaAttribute][$index]['data'] = $mediaUrl;
                        }
                    }
                }
            }
        }

        return $productStandard;
    }

    /**
     * Get url version of local path for a media attribute
     *
     * @param string $path
     *
     * @return string
     */
    protected function getUrlByLocalPath($path)
    {
        return sprintf('%s%s', $this->mediaUrlPrefix, $path);
    }
}
