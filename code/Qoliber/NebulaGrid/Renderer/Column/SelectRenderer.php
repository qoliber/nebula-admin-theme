<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

/**
 * @api
 *
 * Renders the human label for a cell value using the column's
 * `filterOptions` map (which `\Qoliber\NebulaGrid\Model\ColumnLoader`
 * resolves from `filterOptionsSource`). Same lookup the column's filter
 * dropdown uses, so the row display and the filter UI stay in sync.
 *
 * Use case: a column stored as an internal value (a class FQCN, a status
 * code, etc.) where the admin should see the friendly label — without
 * needing a separate badge style.
 *
 * Grid JSON:
 *   "instance_type": {
 *       "label": "Type",
 *       "type": "select",
 *       "filter": "select",
 *       "filterOptionsSource": "nebula.widget.instance_type"
 *   }
 *
 * Falls back to escaped raw value when no label is registered for it —
 * the admin gets the original value instead of a blank cell.
 */
class SelectRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_select';
    }

    public function getTemplate(): string
    {
        // Empty template → parent's render() returns escaped (string) $item[$key].
        // We override render() below to swap the raw value for a label first.
        return '';
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $item
     */
    public function render(array $column, string $key, array $item): string
    {
        $value   = (string) ($item[$key] ?? '');
        $options = is_array($column['filterOptions'] ?? null) ? $column['filterOptions'] : [];
        $label   = $options[$value] ?? null;

        return $this->escaper->escapeHtml(is_string($label) && $label !== '' ? $label : $value);
    }
}
