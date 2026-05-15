<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Model;

use Qoliber\NebulaComponent\Model\OptionSourceResolver;

/**
 * Builds the runtime column list from a grid definition.
 *
 * Responsibilities:
 *   - sort by `position`;
 *   - resolve each column's `filterOptionsSource` FQCN into a concrete
 *     `filterOptions` label map.
 *
 * Extracted from {@see \Qoliber\NebulaGrid\Block\Grid::getColumns()}.
 */
class ColumnLoader
{
    public function __construct(
        private readonly OptionSourceResolver $optionSourceResolver
    ) {
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, array<string, mixed>>
     */
    public function load(array $definition): array
    {
        $columns = $definition['columns'] ?? [];

        uasort($columns, static function (array $a, array $b): int {
            return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
        });

        foreach ($columns as &$col) {
            if (!empty($col['filterOptionsSource']) && empty($col['filterOptions'])) {
                $col['filterOptions'] = $this->optionSourceResolver->toLabelMap(
                    (string) $col['filterOptionsSource']
                );
            }
        }
        unset($col);

        return $columns;
    }
}
