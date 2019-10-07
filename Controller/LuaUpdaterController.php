<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Controller;

use ClickAndMortar\AdvancedCsvConnectorBundle\Reader\File\Csv\ProductAdvancedReader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * LUA updater controller
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Controller
 */
class LuaUpdaterController
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * LuaUpdaterController constructor.
     *
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Test a given script
     *
     * @param Request $request
     *
     * @throws BadRequestHttpException
     *
     * @return JsonResponse
     */
    public function testAction(Request $request): JsonResponse
    {
        // Check if script is not empty
        $script = $request->get('script');
        if (empty($script)) {
            return new JsonResponse([
                'message' => $this->translator->trans('luaUpdater.validation.empty_script'),
            ], 400);
        }

        // Check if test value is not empty
        $testValue = $request->get('testValue');
        if (empty($testValue)) {
            return new JsonResponse([
                'message' => $this->translator->trans('luaUpdater.validation.empty_test_value'),
            ], 400);
        }

        $lua = new \Lua();
        $lua->assign('attributeValue', $testValue);
        $value = $lua->eval(sprintf(
            "%s\n%s",
            ProductAdvancedReader::LUA_SCRIPT_PREFIX,
            $script
        ));

        return new JsonResponse(['value' => $value]);
    }
}
