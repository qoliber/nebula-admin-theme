<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Form;

/**
 * Builds the sequence of renderable "nodes" for the SimpleForm block from a
 * flat list of fieldsets.
 *
 * Three layout dialects supported (most expressive last):
 *
 *   1. No layout — single column stack. Fieldsets render in declaration order.
 *
 *   2. settings.layout=two-column + per-fieldset `column: left|right|third|full`.
 *      Legacy: groups into one row of 2 or 3 equal-width columns, then stacks
 *      every full-width fieldset under it.
 *
 *   3. settings.layout=two-column + per-fieldset `row: <key>` + `width: "3/4"`.
 *      Modern: multiple rows, custom widths per fieldset (tailwind fractions).
 *      Fieldsets without a `row` fall back to the legacy column path so both
 *      dialects can coexist in one JSON during migration.
 *
 * Node shapes emitted:
 *   - Real fieldset.
 *   - Row marker: ['__marker' => 'row_start'|'col_break'|'row_end',
 *                  '__width' => '1/2' (col_break / row_start only)].
 */
class FieldsetLayoutBuilder
{
    /**
     * @param array<string, array<string, mixed>> $fieldsets
     * @return array<int, array<string, mixed>>
     */
    public function buildFromFieldsets(array $fieldsets, string $formLayout): array
    {
        if ($formLayout !== 'two-column') {
            return array_values($fieldsets);
        }

        // Phase 1: split fieldsets into explicit-row groups, a legacy
        // left/right/third group, and a tail of full-width stacked items.
        $explicitRows = [];     // [rowKey => [fieldset, …]]
        $legacy = ['left' => [], 'right' => [], 'third' => []];
        $fullTail = [];

        foreach ($fieldsets as $fieldset) {
            if (isset($fieldset['row'])) {
                $explicitRows[(string) $fieldset['row']][] = $fieldset;
                continue;
            }

            $column = $fieldset['column'] ?? 'full';
            if (in_array($column, ['left', 'right', 'third'], true)) {
                $legacy[$column][] = $fieldset;
                continue;
            }

            $fullTail[] = $fieldset;
        }

        $nodes = [];

        // Phase 2: emit explicit-row groups in declaration order.
        foreach ($explicitRows as $row) {
            $nodes = array_merge($nodes, $this->emitRow($row));
        }

        // Phase 3: legacy left/right/third — kept as one row for back-compat.
        $hasLegacy = $legacy['left'] !== [] || $legacy['right'] !== [] || $legacy['third'] !== [];
        if ($hasLegacy) {
            $row = array_merge($legacy['left'], $legacy['right'], $legacy['third']);
            $nodes = array_merge($nodes, $this->emitRow($row));
        }

        // Phase 4: tail of full-width fieldsets.
        foreach ($fullTail as $fieldset) {
            $nodes[] = $fieldset;
        }

        return $nodes;
    }

    /**
     * @param array<int, array<string, mixed>> $row
     * @return array<int, array<string, mixed>>
     */
    private function emitRow(array $row): array
    {
        if ($row === []) {
            return [];
        }

        if (count($row) === 1) {
            // A single-fieldset "row" is just the fieldset, full-width.
            return [$row[0]];
        }

        $defaultWidth = $this->equalWidth(count($row));
        $nodes = [];
        $nodes[] = [
            '__marker' => 'row_start',
            '__cols' => count($row),
            '__width' => (string) ($row[0]['width'] ?? $defaultWidth),
        ];
        $nodes[] = $row[0];

        for ($i = 1, $n = count($row); $i < $n; $i++) {
            $nodes[] = [
                '__marker' => 'col_break',
                '__width' => (string) ($row[$i]['width'] ?? $defaultWidth),
            ];
            $nodes[] = $row[$i];
        }

        $nodes[] = ['__marker' => 'row_end'];

        return $nodes;
    }

    private function equalWidth(int $cols): string
    {
        return match ($cols) {
            2 => '1/2',
            3 => '1/3',
            4 => '1/4',
            default => 'full',
        };
    }
}
