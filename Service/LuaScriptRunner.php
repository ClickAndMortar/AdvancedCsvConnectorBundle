<?php

namespace ClickAndMortar\AdvancedCsvConnectorBundle\Service;

use ClickAndMortar\AdvancedCsvConnectorBundle\Doctrine\ORM\Repository\LuaUpdaterRepository;
use ClickAndMortar\AdvancedCsvConnectorBundle\Entity\LuaUpdater;

/**
 * Centralized LUA script execution for import/export mappings.
 *
 * Exposes the shared sandbox prefix, wraps the `new \Lua()` / assign / eval
 * sequence used by the Reader, Writer, Validator and test Controller, and
 * implements the `_data_*` shared-table convention (see README in Phase 2).
 */
class LuaScriptRunner
{
    /**
     * Whitelist of variables and standard-library functions exposed to every
     * LUA script executed through this service.
     *
     * `attributeValue` is the scalar being transformed.
     * `originalItem` is the full CSV row (import) or product item (export),
     * exposed as an associative LUA table.
     * `loadData` is a PHP callback returning a pre-loaded `_data_*` table.
     */
    const SCRIPT_PREFIX = 'local _ENV = { attributeValue = attributeValue, originalItem = originalItem, loadData = loadData, string = string, math = math, ipairs = ipairs, load = load, next = next, pairs = pairs, rawequal = rawequal, rawget = rawget, rawlen = rawlen, rawset = rawset, select = select, tonumber = tonumber, tostring = tostring, type = type}';

    protected LuaUpdaterRepository $luaUpdaterRepository;

    /**
     * Per-process cache of pre-executed `_data_*` scripts.
     * Key = updater code, value = the table returned by the script.
     */
    protected array $dataCache = [];

    /**
     * Per-process cache of LuaUpdater entities (to avoid hitting the DB
     * repeatedly inside a batch job).
     */
    protected array $luaUpdaters = [];

    public function __construct(LuaUpdaterRepository $luaUpdaterRepository)
    {
        $this->luaUpdaterRepository = $luaUpdaterRepository;
    }

    /**
     * Execute a LUA script with the shared sandbox.
     *
     * @param string $script       The raw LUA script body.
     * @param mixed  $attributeValue The value being transformed.
     * @param array  $originalItem Full row/item available to the script.
     *
     * @return mixed The value returned by the script, or false on error.
     *
     * @throws \RuntimeException If the `lua` PHP extension is not loaded.
     */
    public function run(string $script, $attributeValue, array $originalItem = [])
    {
        $this->assertLuaExtensionLoaded();

        $lua = new \Lua();
        $lua->assign('attributeValue', $attributeValue);
        $lua->assign('originalItem', $originalItem);

        foreach ($this->resolveDataDependencies($script) as $code => $table) {
            $lua->assign($code, $table);
        }

        $lua->registerCallback('loadData', function ($code) {
            return $this->dataCache[$code] ?? null;
        });

        return $lua->eval(sprintf("%s\n%s", self::SCRIPT_PREFIX, $script));
    }

    /**
     * Validate a LUA script by running it with dummy values.
     * Used by LuaScriptValidator and the Controller test endpoint.
     *
     * @return bool True if the script evaluates without error.
     */
    public function validate(string $script): bool
    {
        if (empty($script)) {
            return false;
        }

        try {
            $result = $this->run($script, '1', ['sku' => 'TEST']);
        } catch (\Throwable $e) {
            return false;
        }

        return $result !== false;
    }

    /**
     * Scan the script for `loadData("…")` calls and pre-execute the
     * referenced data scripts once, caching their returned tables.
     *
     * Any LuaUpdater code can be referenced — the script it points to is
     * expected to return a LUA table (naming convention: data scripts are
     * typically prefixed with `getData…` or `_data_…`, but this is not
     * enforced).
     *
     * @return array<string, mixed> Map of updater code => LUA table.
     */
    protected function resolveDataDependencies(string $script): array
    {
        if (!preg_match_all('/loadData\(\s*["\']([^"\']+)["\']\s*\)/', $script, $matches)) {
            return [];
        }

        $resolved = [];
        foreach (array_unique($matches[1]) as $code) {
            if (!array_key_exists($code, $this->dataCache)) {
                $this->dataCache[$code] = $this->executeDataScript($code);
            }
            $resolved[$code] = $this->dataCache[$code];
        }

        return $resolved;
    }

    /**
     * Execute a data script in its own sandbox and return the table it
     * produces. No `originalItem` / `loadData` chain here — data scripts are
     * expected to be pure `return { … }` declarations.
     */
    protected function executeDataScript(string $code)
    {
        $updater = $this->findUpdater($code);
        if ($updater === null) {
            return null;
        }

        $lua = new \Lua();
        $lua->assign('attributeValue', null);
        $lua->assign('originalItem', []);

        return $lua->eval(sprintf("%s\n%s", self::SCRIPT_PREFIX, $updater->getScript()));
    }

    protected function findUpdater(string $code): ?LuaUpdater
    {
        if (!array_key_exists($code, $this->luaUpdaters)) {
            $this->luaUpdaters[$code] = $this->luaUpdaterRepository->findOneBy(['code' => $code]);
        }

        return $this->luaUpdaters[$code];
    }

    protected function assertLuaExtensionLoaded(): void
    {
        if (!extension_loaded('lua')) {
            throw new \RuntimeException(
                'The "lua" PHP extension is required to execute LUA updaters. '
                . 'Install php-lua (pecl install lua) and enable it in php.ini.'
            );
        }
    }
}
