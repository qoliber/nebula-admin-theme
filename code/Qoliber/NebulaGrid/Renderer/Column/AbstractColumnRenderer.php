<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use Qoliber\NebulaComponent\Api\ColumnRendererInterface;
use Qoliber\NebulaComponent\Model\PhtmlRenderer;

/**
 * Base class: shared escaping + phtml delegation for column renderers.
 * Implementations override `getTemplate()` and optionally `toViewData()`.
 */
abstract class AbstractColumnRenderer implements ColumnRendererInterface
{
    public function __construct(
        protected readonly Escaper $escaper,
        protected readonly UrlInterface $urlBuilder,
        protected readonly LayoutInterface $layout,
        protected readonly PhtmlRenderer $phtmlRenderer
    ) {
    }

    public function render(array $column, string $key, array $item): string
    {
        $template = $this->getTemplate();

        if ($template === '') {
            return $this->escaper->escapeHtml((string) ($item[$key] ?? ''));
        }

        return $this->phtmlRenderer->render(
            $this->layout,
            $template,
            $this->toViewData($column, $key, $item)
        );
    }

    /**
     * Data exposed to the phtml partial. Override to shape the cell's context.
     *
     * @param array<string, mixed> $column
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function toViewData(array $column, string $key, array $item): array
    {
        return [
            'column' => $column,
            'key' => $key,
            'item' => $item,
            'value' => $item[$key] ?? null,
        ];
    }

    /**
     * Expand `{{field}}` placeholders in a URL template against the row data.
     *
     * @param array<string, mixed> $item
     */
    protected function interpolate(string $template, array $item): string
    {
        return (string) preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            static fn (array $m): string => (string) ($item[$m[1]] ?? ''),
            $template
        );
    }
}
