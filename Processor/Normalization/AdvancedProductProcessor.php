<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Processor\Normalization;

use Akeneo\Component\Batch\Job\JobInterface;
use Akeneo\Component\StorageUtils\Cache\CacheClearerInterface;
use Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Pim\Component\Catalog\Repository\AttributeRepositoryInterface;
use Pim\Component\Catalog\ValuesFiller\EntityWithFamilyValuesFillerInterface;
use Pim\Component\Connector\Processor\BulkMediaFetcher;
use Pim\Component\Connector\Processor\Normalization\ProductProcessor;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Pim\Component\Catalog\Repository\ChannelRepositoryInterface;

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
     * @param NormalizerInterface                   $normalizer
     * @param ChannelRepositoryInterface            $channelRepository
     * @param AttributeRepositoryInterface          $attributeRepository
     * @param ObjectDetacherInterface               $detacher
     * @param BulkMediaFetcher                      $mediaFetcher
     * @param EntityWithFamilyValuesFillerInterface $productValuesFiller
     * @param CacheClearerInterface                 $cacheClearer
     * @param string                                $mediaUrlPrefix
     */
    public function __construct(
        NormalizerInterface $normalizer,
        ChannelRepositoryInterface $channelRepository,
        AttributeRepositoryInterface $attributeRepository,
        ObjectDetacherInterface $detacher,
        BulkMediaFetcher $mediaFetcher,
        EntityWithFamilyValuesFillerInterface $productValuesFiller,
        CacheClearerInterface $cacheClearer = null,
        $mediaUrlPrefix
    )
    {
        parent::__construct($normalizer, $channelRepository, $attributeRepository, $detacher, $mediaFetcher, $productValuesFiller, $cacheClearer);
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

        if (null !== $this->cacheClearer) {
            $this->cacheClearer->clear();
        } else {
            // TODO Remove $this->detacher, the upper condition and update the constructor on merge to master
            $this->detacher->detach($product);
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
