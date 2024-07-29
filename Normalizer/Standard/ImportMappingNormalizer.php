<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Normalizer\Standard;

use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\ImportMapping;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Import mapping normalizer
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Normalizer\Standard
 */
class ImportMappingNormalizer implements NormalizerInterface
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
            'id'                 => $entity->getId(),
            'label'              => $entity->getLabel(),
            'code'               => $entity->getCode(),
            'mappingAsJson'      => $entity->getMappingAsJson(),
            'completeCallback'   => $entity->getCompleteCallback(),
            'initializeCallback' => $entity->getInitializeCallback(),
            'flushCallback'      => $entity->getFlushCallback(),
            'itemsLimit'         => $entity->getItemsLimit(),
            'onlyUpdate'         => $entity->getOnlyUpdate(),
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
        return $data instanceof ImportMapping && in_array($format, $this->supportedFormats);
    }
}
