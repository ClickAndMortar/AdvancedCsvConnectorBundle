<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Validator;

use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\LuaUpdater;
use ClickAndMortar\AdvancedCsvConnectorBundle\Service\LuaScriptRunner;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Check validity of Lua script by running script with test data
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Validator
 */
class LuaScriptValidator extends ConstraintValidator
{
    protected LuaScriptRunner $luaScriptRunner;

    public function __construct(LuaScriptRunner $luaScriptRunner)
    {
        $this->luaScriptRunner = $luaScriptRunner;
    }

    /**
     * @param LuaUpdater $luaUpdater
     * @param Constraint $constraint
     */
    public function validate($luaUpdater, Constraint $constraint)
    {
        $luaScript = $luaUpdater->getScript();

        if (empty($luaScript)) {
            $this->context->addViolation(
                'candm_advanced_csv_connector.lua_updater.validation.lua_script.empty'
            );

            return;
        }

        if (!$this->luaScriptRunner->validate($luaScript)) {
            $this->context->addViolation(
                'candm_advanced_csv_connector.lua_updater.validation.lua_script.error'
            );
        }
    }
}
