<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/**
 * A column renderer knows how to:
 *   1. Turn a (column, item) pair into HTML (server-side grid rendering).
 *   2. Identify the matching JS Alpine component name (for client-side enhancements).
 *   3. Identify an optional phtml partial so `render()` stays markup-in-template.
 *
 * Implementations live under `Qoliber\NebulaGrid\Renderer\Column\*Renderer` and
 * are wired into `Qoliber\NebulaComponent\Model\RendererPool` via `etc/di.xml`.
 */
interface ColumnRendererInterface
{
    /**
     * Alpine component name, e.g. `nebulaColumn_badge`. Used by the grid's
     * template when the column is enhanced client-side (rare today, but part
     * of the contract for future extensions).
     */
    public function getComponentName(): string;

    /**
     * Optional phtml template reference (`Vendor_Module::column/foo.phtml`).
     * Return empty string if the renderer composes HTML purely in PHP.
     */
    public function getTemplate(): string;

    /**
     * Render a single cell.
     *
     * @param array<string, mixed> $column The column definition from the grid JSON.
     * @param string $key Column key (array index in $column + $item).
     * @param array<string, mixed> $item The full data row.
     */
    public function render(array $column, string $key, array $item): string;
}
