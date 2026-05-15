<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Definition;

use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\Cache\Type\NebulaDefinitions;

/**
 * Cache-aware resolver for Nebula definitions.
 *
 * Flow:
 * - developer mode: Loader → Merger → SnippetResolver, no cache.
 * - default (cache miss): resolve live, then cache under the nebula_definitions tag.
 * - default (cache hit): deserialize compiled payload.
 * - production: compiled manifest at generated/nebula/definitions.php is the only legal
 *   source; if missing, throw a clear \RuntimeException telling the operator to run
 *   `bin/magento nebula:compile`.
 */
class Resolver implements DefinitionResolverInterface
{
    /** @var string cache key prefix for developer/default runtime lookups */
    public const CACHE_PREFIX = 'nebula_definition_';

    /** @var string tag attached to every cache entry (mirrors NebulaDefinitions::CACHE_TAG) */
    public const CACHE_TAG = NebulaDefinitions::CACHE_TAG;

    /** @var string relative path (from repo/BP root) to the compiled manifest */
    public const COMPILED_MANIFEST_RELATIVE_PATH = 'generated/nebula/definitions.php';

    /** @var array<string, array>|null lazily loaded compiled manifest */
    private static ?array $compiledManifest = null;

    /** @var string|null */
    private ?string $modulesHash = null;

    public function __construct(
        private readonly \Qoliber\NebulaComponent\Model\Definition\Loader $loader,
        private readonly \Qoliber\NebulaComponent\Model\Definition\Merger $merger,
        private readonly \Magento\Framework\App\CacheInterface $cache,
        private readonly \Magento\Framework\Serialize\Serializer\Json $json,
        private readonly SnippetResolverInterface $snippetResolver,
        private readonly \Magento\Framework\App\State $appState,
        private readonly \Magento\Framework\Module\ModuleListInterface $moduleList,
        private readonly \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    ) {
        // `cache` is the default Magento\Framework\App\Cache implementation. Entries are
        // tagged with NEBULA_DEFINITIONS so `bin/magento cache:clean nebula_definitions`
        // and the Resolver's own clearCache() both invalidate them cleanly.
    }

    /**
     * @param string $type
     * @param string $id
     * @return array
     */
    public function resolve(string $type, string $id): array
    {
        $mode = $this->safeGetMode();

        // Developer mode: always resolve live, never read/write cache.
        if ($mode === \Magento\Framework\App\State::MODE_DEVELOPER) {
            return $this->resolveLive($type, $id);
        }

        // Production mode: compiled manifest is the only legal source.
        if ($mode === \Magento\Framework\App\State::MODE_PRODUCTION) {
            return $this->resolveFromManifest($type, $id);
        }

        // Default mode: cache-through with module-hash invalidation.
        $key = self::CACHE_PREFIX . sha1($type . ':' . $id . ':' . $this->modulesHash());

        $payload = $this->cache->load($key);
        if (is_string($payload) && $payload !== '') {
            try {
                $decoded = $this->json->unserialize($payload);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable) {
                // Fall through to recompute on corrupted cache.
            }
        }

        $resolved = $this->resolveLive($type, $id);

        $this->cache->save(
            $this->json->serialize($resolved),
            $key,
            [self::CACHE_TAG],
            null
        );

        return $resolved;
    }

    /**
     * @param string|null $type
     * @param string|null $id
     * @return void
     */
    public function clearCache(?string $type = null, ?string $id = null): void
    {
        if ($type !== null && $id !== null) {
            $this->cache->remove(self::CACHE_PREFIX . sha1($type . ':' . $id . ':' . $this->modulesHash()));
            // Also clear the legacy key format in case anything upstream references it.
            $this->cache->remove(self::CACHE_PREFIX . $type . '_' . $id);
            return;
        }

        $this->cache->clean([self::CACHE_TAG]);
        self::$compiledManifest = null;
    }

    /**
     * Live resolve (Loader → Merger → SnippetResolver). Exposed so CompileCommand can populate
     * the manifest without re-implementing the resolve flow.
     *
     * @param string $type
     * @param string $id
     * @return array
     */
    public function resolveLive(string $type, string $id): array
    {
        $definitions = $this->loader->load($type, $id);
        $merged = $this->merger->merge($definitions);

        if (isset($merged['layout'])) {
            $merged = $this->snippetResolver->resolveLayout($merged);
        }

        return $merged;
    }

    /**
     * Stable sha1 over the ordered list of enabled module names. A module enable/disable
     * changes the hash and invalidates every existing cache entry transparently.
     *
     * @return string
     */
    public function modulesHash(): string
    {
        if ($this->modulesHash !== null) {
            return $this->modulesHash;
        }

        $names = $this->moduleList->getNames();
        // Preserve caller-provided order (module sequence matters for merge order), stringify
        // with a separator guaranteed not to appear in module names.
        return $this->modulesHash = sha1(implode("\n", $names));
    }

    /**
     * @param string $type
     * @param string $id
     * @return array
     */
    private function resolveFromManifest(string $type, string $id): array
    {
        $manifest = $this->loadCompiledManifest();
        $key = $type . ':' . $id;

        if (!array_key_exists($key, $manifest)) {
            throw new \RuntimeException(sprintf(
                'Nebula definition "%s" is not in the compiled manifest. '
                . 'Run "bin/magento nebula:compile" (or "bin/magento setup:di:compile") and retry.',
                $key
            ));
        }

        return $manifest[$key];
    }

    /**
     * @return array<string, array>
     */
    private function loadCompiledManifest(): array
    {
        if (self::$compiledManifest !== null) {
            return self::$compiledManifest;
        }

        try {
            $root = $this->directoryList->getRoot();
        } catch (\Throwable) {
            $root = getcwd() ?: '';
        }

        $path = rtrim($root, '/') . '/' . self::COMPILED_MANIFEST_RELATIVE_PATH;

        if (!is_file($path)) {
            throw new \RuntimeException(
                'Nebula compiled manifest is missing at ' . $path
                . '. Run "bin/magento nebula:compile" before serving admin requests in production mode.'
            );
        }

        /** @var mixed $data */
        $data = require $path;

        if (!is_array($data)) {
            throw new \RuntimeException(
                'Nebula compiled manifest at ' . $path . ' did not return an array.'
            );
        }

        /** @var array<string, array> $data */
        return self::$compiledManifest = $data;
    }

    /**
     * App\State::getMode() throws if not initialized; tolerate that for CLI/test contexts.
     */
    private function safeGetMode(): string
    {
        try {
            return (string) $this->appState->getMode();
        } catch (\Throwable) {
            return \Magento\Framework\App\State::MODE_DEFAULT;
        }
    }
}
