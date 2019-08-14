<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Validator;

use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\LuaUpdater;
use ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv\ProductAdvancedReader;
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
    /**
     * Test attribute value
     *
     * @var string
     */
    const ATTRIBUTE_VALUE_TEST = 'test';

    /**
     * @param LuaUpdater $luaUpdater
     * @param Constraint $constraint
     */
    public function validate($luaUpdater, Constraint $constraint)
    {
        $luaScript = $luaUpdater->getScript();

        // Check if script is empty
        if (empty($luaScript)) {
            $this->context->addViolation(
                'candm_advanced_csv_connector.lua_updater.validation.lua_script.empty'
            );
        }

        // Check script validity
        $lua = new \Lua();
        $lua->assign('attributeValue', self::ATTRIBUTE_VALUE_TEST);
        $value = $lua->eval(sprintf(
            "%s\n%s",
            ProductAdvancedReader::LUA_SCRIPT_PREFIX,
            $luaScript
        ));
        if ($value === false) {
            $this->context->addViolation(
                'candm_advanced_csv_connector.lua_updater.validation.lua_script.error'
            );
        }
    }
}
