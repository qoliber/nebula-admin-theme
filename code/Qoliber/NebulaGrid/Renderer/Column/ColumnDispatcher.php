<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

use Qoliber\NebulaComponent\Model\RendererPool;

/**
 * Decides which renderer renders a column:
 *   1. `renderer: "snippet.xyz"` → {@see SnippetRenderer}.
 *   2. `template: "..."` or `renderer: "Vendor_Module::..."` → {@see TemplateRenderer}.
 *   3. Otherwise → {@see RendererPool::getColumnRenderer($column['type'])}.
 */
class ColumnDispatcher
{
    public function __construct(
        private readonly RendererPool $rendererPool,
        private readonly SnippetRenderer $snippetRenderer,
        private readonly TemplateRenderer $templateRenderer
    ) {
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $item
     */
    public function render(array $column, string $key, array $item): string
    {
        $renderer = (string) ($column['renderer'] ?? '');

        if (str_starts_with($renderer, 'snippet.')) {
            return $this->snippetRenderer->render($column, $key, $item);
        }

        if (!empty($column['template']) || str_contains($renderer, '::')) {
            return $this->templateRenderer->render($column, $key, $item);
        }

        $type = (string) ($column['type'] ?? 'text');

        return $this->rendererPool->getColumnRenderer($type)->render($column, $key, $item);
    }
}
