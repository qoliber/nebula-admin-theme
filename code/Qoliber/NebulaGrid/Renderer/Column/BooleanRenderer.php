<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

class BooleanRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_boolean';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/boolean.phtml';
    }

    protected function toViewData(array $column, string $key, array $item): array
    {
        return [
            'column' => $column,
            'key' => $key,
            'item' => $item,
            'value' => (bool) ($item[$key] ?? false),
        ];
    }
}
