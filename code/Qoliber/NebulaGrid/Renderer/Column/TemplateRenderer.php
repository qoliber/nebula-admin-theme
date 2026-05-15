<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

/**
 * Renderer for columns that directly specify a phtml template via the
 * `renderer` key (`Vendor_Module::column/foo.phtml`) or the `template` key
 * after a snippet has already been merged in.
 */
class TemplateRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_template';
    }

    public function getTemplate(): string
    {
        return '';
    }

    public function render(array $column, string $key, array $item): string
    {
        $template = (string) ($column['template'] ?? '');

        if (
            $template === '' && !empty($column['renderer']) && is_string($column['renderer'])
            && str_contains($column['renderer'], '::')
        ) {
            $template = $column['renderer'];
        }

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
