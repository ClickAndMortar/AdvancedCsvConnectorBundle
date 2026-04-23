<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Controller;

use ClickAndMortar\AdvancedCsvConnectorBundle\Service\LuaScriptRunner;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * LUA updater controller
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AdvancedCsvConnectorBundle\Controller
 */
class LuaUpdaterController
{
    protected TranslatorInterface $translator;

    protected LuaScriptRunner $luaScriptRunner;

    public function __construct(TranslatorInterface $translator, LuaScriptRunner $luaScriptRunner)
    {
        $this->translator      = $translator;
        $this->luaScriptRunner = $luaScriptRunner;
    }

    /**
     * Test a given script.
     *
     * Accepts `script`, `testValue`, and optionally `originalItem` (object)
     * via a JSON request body.
     */
    public function testAction(Request $request): JsonResponse
    {
        $payload = $this->readPayload($request);

        $script = (string) ($payload['script'] ?? '');
        if ($script === '') {
            return new JsonResponse([
                'message' => $this->translator->trans('luaUpdater.validation.empty_script'),
            ], 400);
        }

        $testValue = $payload['testValue'] ?? '';
        if ($testValue === '' || $testValue === null) {
            return new JsonResponse([
                'message' => $this->translator->trans('luaUpdater.validation.empty_test_value'),
            ], 400);
        }

        $originalItem = $this->normalizeOriginalItem($payload['originalItem'] ?? null);

        try {
            $value = $this->luaScriptRunner->run($script, $testValue, $originalItem);
        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }

        return new JsonResponse(['value' => $value]);
    }

    /**
     * Merge JSON body, form fields, and query string into a single array so the
     * caller can send a payload via any of them.
     */
    protected function readPayload(Request $request): array
    {
        $body = [];
        $content = $request->getContent();
        if ($content !== '' && str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return array_replace($request->query->all(), $request->request->all(), $body);
    }

    protected function normalizeOriginalItem($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
