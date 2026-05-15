<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

class LinkRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_link';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/link.phtml';
    }

    protected function toViewData(array $column, string $key, array $item): array
    {
        return [
            'column' => $column,
            'key' => $key,
            'item' => $item,
            'value' => $item[$key] ?? null,
            'href' => $this->interpolate((string) ($column['href'] ?? '#'), $item),
        ];
    }
}
