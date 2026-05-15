<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Api;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Contract every widget-chooser ViewModel must implement. Locked-in so
 * the registry can typecheck at lookup time — a misregistered
 * `view_model` FQCN fails loudly here instead of mysteriously rendering
 * the wrong thing.
 *
 * Two responsibilities:
 *
 *   - `search()` populates the chooser modal's row list. Server-rendered
 *     for the first page; live AJAX pagination is a future enhancement.
 *
 *   - `getByValue()` resolves a single stored value back to a row,
 *     used to render the "currently selected" pill on the wizard when
 *     editing an existing widget. The argument is whatever the chooser
 *     emits as the row's `value` (a bare id, a `category/<id>` path,
 *     etc.) — the implementation owns the format.
 */
interface WidgetChooserViewModelInterface extends ArgumentInterface
{
    /**
     * @return array{
     *     total: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function search(string $query, int $page, int $pageSize): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getByValue(string $value): ?array;
}
