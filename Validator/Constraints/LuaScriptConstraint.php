<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Lua script constraint
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Validator\Constraints
 */
class LuaScriptConstraint extends Constraint
{
    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return 'candm_advanced_csv_connector.validator.lua_script';
    }
}
