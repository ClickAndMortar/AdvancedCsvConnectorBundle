<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Entity;

use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;

/**
 * Import mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Entity
 */
class ImportMapping extends AbstractCustomEntity
{
    /**
     * Label
     *
     * @var string
     */
    protected $label;

    /**
     * Mapping as JSON
     *
     * @var string
     */
    protected $mappingAsJson;

    /**
     * Complete callback
     *
     * @var string
     */
    protected $completeCallback;

    /**
     * Get label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set label
     *
     * @param string $label
     *
     * @return Brand
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return string
     */
    public function getMappingAsJson()
    {
        return $this->mappingAsJson;
    }

    /**
     * @param string $mappingAsJson
     *
     * @return Mapping
     */
    public function setMappingAsJson($mappingAsJson)
    {
        $this->mappingAsJson = $mappingAsJson;

        return $this;
    }

    /**
     * @return string
     */
    public function getCompleteCallback(): string
    {
        return $this->completeCallback;
    }

    /**
     * @param string $completeCallback
     *
     * @return ImportMapping
     */
    public function setCompleteCallback(string $completeCallback): ImportMapping
    {
        $this->completeCallback = $completeCallback;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getLabelProperty(): string
    {
        return 'label';
    }

    /**
     * Returns the custom entity name used in the configuration
     * Used to map row actions on datagrid
     *
     * @return string
     */
    public function getCustomEntityName(): string
    {
        return 'importMapping';
    }

    /**
     * Get reference value
     *
     * @return string
     */
    public function getReference(): string
    {
        return $this->getCode();
    }
}
