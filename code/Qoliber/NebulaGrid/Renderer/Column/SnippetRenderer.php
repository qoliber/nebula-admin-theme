<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use Qoliber\NebulaComponent\Api\SnippetResolverInterface;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;

/**
 * Dispatches a column whose `renderer` starts with `snippet.` — resolves the
 * named snippet and renders its template with the standard cell context.
 */
class SnippetRenderer extends AbstractColumnRenderer
{
    public function __construct(
        Escaper $escaper,
        UrlInterface $urlBuilder,
        LayoutInterface $layout,
        PhtmlRenderer $phtmlRenderer,
        private readonly SnippetResolverInterface $snippetResolver
    ) {
        parent::__construct($escaper, $urlBuilder, $layout, $phtmlRenderer);
    }

    public function getComponentName(): string
    {
        return 'nebulaColumn_snippet';
    }

    public function getTemplate(): string
    {
        // Intentionally empty — template is resolved per-column from the snippet.
        return '';
    }

    public function render(array $column, string $key, array $item): string
    {
        $renderer = (string) ($column['renderer'] ?? '');
        $snippetId = str_starts_with($renderer, 'snippet.') ? substr($renderer, 8) : '';

        if ($snippetId !== '') {
            $snippet = $this->snippetResolver->resolve($snippetId);
            $column = array_merge($snippet, $column);
            unset($column['renderer']);
        }

        $template = (string) ($column['template'] ?? '');

        if ($template === '') {
            return $this->escaper->escapeHtml((string) ($item[$key] ?? ''));
        }

        return $this->phtmlRenderer->render(
            $this->layout,
            $template,
            [
                'column' => $column,
                'key' => $key,
                'item' => $item,
                'value' => $item[$key] ?? null,
            ]
        );
    }
}
