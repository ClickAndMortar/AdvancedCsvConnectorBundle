<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Entity;

use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;

/**
 * Mapping
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Entity
 */
class Mapping extends AbstractCustomEntity
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
        return 'mapping';
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