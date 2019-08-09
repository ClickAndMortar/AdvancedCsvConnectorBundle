<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Normalizer\Standard;

use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\LuaUpdater;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Lua Updater
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Normalizer\Standard
 */
class LuaUpdaterNormalizer implements NormalizerInterface
{
    /**
     * @var string[]
     */
    protected $supportedFormats = ['standard'];

    /**
     * @param LuaUpdater $entity
     * @param null       $format
     * @param array      $context
     *
     * @return array
     */
    public function normalize($entity, $format = null, array $context = [])
    {
        $mapping = [
            'id'     => $entity->getId(),
            'label'  => $entity->getLabel(),
            'code'   => $entity->getCode(),
            'script' => $entity->getScript(),
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
        return $data instanceof LuaUpdater && in_array($format, $this->supportedFormats);
    }
}
