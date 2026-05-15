<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

class DateRenderer extends AbstractColumnRenderer
{
    public function getComponentName(): string
    {
        return 'nebulaColumn_date';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/date.phtml';
    }

    protected function toViewData(array $column, string $key, array $item): array
    {
        $raw = $item[$key] ?? null;
        $format = (string) ($column['format'] ?? 'M j, Y');
        $formatted = '';
        $parseFailed = false;

        if ($raw) {
            try {
                $formatted = (new \DateTimeImmutable((string) $raw))->format($format);
            } catch (\Exception) {
                $parseFailed = true;
                $formatted = (string) $raw;
            }
        }

        return [
            'column' => $column,
            'key' => $key,
            'item' => $item,
            'value' => $raw,
            'formatted' => $formatted,
            'parse_failed' => $parseFailed,
        ];
    }
}
