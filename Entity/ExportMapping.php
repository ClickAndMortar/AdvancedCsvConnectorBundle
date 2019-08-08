<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Entity;

use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;

/**
 * Export mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Entity
 */
class ExportMapping extends AbstractCustomEntity
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
    public function getCompleteCallback()
    {
        return $this->completeCallback;
    }

    /**
     * @param string $completeCallback
     *
     * @return ImportMapping
     */
    public function setCompleteCallback($completeCallback)
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
        return 'exportMapping';
    }

    /**
     * Get reference value
     *
     * @return string
     */
    public function getReference()
    {
        return $this->getCode();
    }

    /**
     * Get complete mapping
     *
     * @return string
     */
    public function getMappingAsArray()
    {
        // Get columns mapping
        $columnsMapping = !empty($this->getMappingAsJson()) ? $this->getMappingAsJson() : '[]';

        $mapping = [
            'columns' => json_decode($columnsMapping, true),
        ];

        // And complete callback if necessary
        if (!empty($this->getCompleteCallback())) {
            $mapping['completeCallback'] = $this->getCompleteCallback();
        }

        return $mapping;
    }
}
