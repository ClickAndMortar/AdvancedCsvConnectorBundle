<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Normalizer\Standard;

use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\ExportMapping;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Export mapping normalizer
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Normalizer\Standard
 */
class ExportMappingNormalizer implements NormalizerInterface
{
    /**
     * @var string[]
     */
    protected $supportedFormats = ['standard'];

    /**
     * @param ImportMapping $entity
     * @param null          $format
     * @param array         $context
     *
     * @return array
     */
    public function normalize($entity, $format = null, array $context = [])
    {
        $mapping = [
            'id'               => $entity->getId(),
            'label'            => $entity->getLabel(),
            'code'             => $entity->getCode(),
            'mappingAsJson'    => $entity->getMappingAsJson(),
            'completeCallback' => $entity->getCompleteCallback(),
        ];

        return $mapping;
    }

    /**
     * @param mixed $data
     * @param null  $format
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof ExportMapping && in_array($format, $this->supportedFormats);
    }
}
