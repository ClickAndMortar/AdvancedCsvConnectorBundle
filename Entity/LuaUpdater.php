<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Entity;

use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;

/**
 * Lua Updater
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Entity
 */
class LuaUpdater extends AbstractCustomEntity
{
    /**
     * Label
     *
     * @var string
     */
    protected $label;

    /**
     * Script to update value
     *
     * @var string
     */
    protected $script;

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
    public function getScript()
    {
        return $this->script;
    }

    /**
     * @param string $script
     *
     * @return LuaUpdater
     */
    public function setScript($script)
    {
        $this->script = $script;

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
        return 'luaUpdater';
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
