<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Form;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as DirReader;
use Magento\Framework\Module\ModuleListInterface;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;

/**
 * Builds a reverse lookup of admin full-action-name => { form_id, replaces }
 * from the compiled Nebula form definitions.
 *
 * Form JSON definitions self-describe their wiring via two top-level keys:
 *  - `route`    — string OR array of strings: the admin full-action-name(s) the
 *                 form mounts on (e.g. "cms_block_edit").
 *  - `replaces` — optional legacy Magento block name(s) to remove — string OR
 *                 array of strings (e.g. "cms_block_form", or
 *                 ["adminhtml.user.edit.tabs", "adminhtml.user.edit"]).
 *
 * Enumeration: there is no resolver API to list every definition, so this class
 * discovers all `view/adminhtml/form/*.json` ids across enabled modules — the
 * same discovery strategy {@see \Qoliber\NebulaComponent\Console\Command\CompileCommand}
 * uses — then resolves each via {@see DefinitionResolverInterface} so the merged
 * (and mode-appropriately cached/compiled) payload is read.
 *
 * The map is memoised per-request: the manifest does not change at runtime, and
 * the underlying resolver already handles cross-request caching (cache type in
 * default mode, compiled manifest in production).
 */
class RouteFormMap
{
    /** @var string */
    private const DEFINITION_TYPE = 'form';

    /** @var array<string, array{form_id: string, replaces: array<int, string>}>|null */
    private ?array $map = null;

    public function __construct(
        private readonly DefinitionResolverInterface $definitionResolver,
        private readonly DirReader $dirReader,
        private readonly FileDriver $fileDriver,
        private readonly ModuleListInterface $moduleList,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve a single admin full-action-name to its mounted form, or null.
     *
     * @param string $fullActionName
     * @return array{form_id: string, replaces: array<int, string>}|null
     */
    public function find(string $fullActionName): ?array
    {
        return $this->getMap()[$fullActionName] ?? null;
    }

    /**
     * The whole reverse lookup table.
     *
     * @return array<string, array{form_id: string, replaces: array<int, string>}>
     */
    public function getMap(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = [];

        foreach ($this->discoverFormIds() as $formId) {
            try {
                $definition = $this->definitionResolver->resolve(self::DEFINITION_TYPE, $formId);
            } catch (\Throwable $e) {
                $this->logger->warning('Nebula RouteFormMap: failed to resolve form definition.', [
                    'form_id' => $formId,
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }

            $routes = $this->normaliseRoutes($definition['route'] ?? null);
            if ($routes === []) {
                continue;
            }

            $replaces = $this->normaliseReplaces($definition['replaces'] ?? null);

            foreach ($routes as $route) {
                if (isset($map[$route])) {
                    $this->logger->warning('Nebula RouteFormMap: route claimed by multiple forms.', [
                        'route' => $route,
                        'existing_form_id' => $map[$route]['form_id'],
                        'conflicting_form_id' => $formId,
                    ]);
                    continue;
                }

                $map[$route] = ['form_id' => $formId, 'replaces' => $replaces];
            }
        }

        return $this->map = $map;
    }

    /**
     * Discover every `form` definition id by scanning view/adminhtml/form/*.json
     * across all enabled modules. Mirrors CompileCommand::discoverPairs().
     *
     * @return array<int, string>
     */
    private function discoverFormIds(): array
    {
        $ids = [];

        foreach ($this->moduleList->getNames() as $moduleName) {
            try {
                $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            } catch (\Throwable) {
                continue;
            }

            $dir = $moduleDir . '/view/adminhtml/' . self::DEFINITION_TYPE;

            try {
                if (!$this->fileDriver->isExists($dir)) {
                    continue;
                }
                $entries = $this->fileDriver->readDirectory($dir);
            } catch (\Throwable) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!str_ends_with($entry, '.json')) {
                    continue;
                }
                // Two modules may expose the same id — dedupe; the resolver merges them.
                $id = basename($entry, '.json');
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Normalise the `route` key (string or array of strings) into a list of
     * non-empty full-action-name strings.
     *
     * @param mixed $route
     * @return array<int, string>
     */
    private function normaliseRoutes(mixed $route): array
    {
        if (is_string($route)) {
            $route = [$route];
        }

        if (!is_array($route)) {
            return [];
        }

        $routes = [];
        foreach ($route as $value) {
            if (is_string($value) && $value !== '') {
                // Key by the route string so a value repeated across the array
                // collapses to a single entry; array_values() re-indexes below.
                $routes[$value] = $value;
            }
        }

        return array_values($routes);
    }

    /**
     * Normalise the `replaces` key (string, array of strings, or absent) into a
     * list of non-empty legacy block names. Mirrors {@see normaliseRoutes()}.
     *
     * @param mixed $replaces
     * @return array<int, string>
     */
    private function normaliseReplaces(mixed $replaces): array
    {
        if (is_string($replaces)) {
            $replaces = [$replaces];
        }

        if (!is_array($replaces)) {
            return [];
        }

        $blocks = [];
        foreach ($replaces as $value) {
            if (is_string($value) && $value !== '') {
                // Key by the block name to dedupe repeated entries.
                $blocks[$value] = $value;
            }
        }

        return array_values($blocks);
    }
}
