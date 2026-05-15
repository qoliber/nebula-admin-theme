<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Renderer\Column;

class BadgeRenderer extends AbstractColumnRenderer
{
    /** @var array<string, string> canonical badge tone → Tailwind classes */
    public const BADGE_CLASS_MAP = [
        'success' => 'bg-green-100 text-green-800',
        'error' => 'bg-red-100 text-red-800',
        'warning' => 'bg-yellow-100 text-yellow-800',
        'info' => 'bg-blue-100 text-blue-800',
        'neutral' => 'bg-gray-100 text-gray-800',
    ];

    public function getComponentName(): string
    {
        return 'nebulaColumn_badge';
    }

    public function getTemplate(): string
    {
        return 'Qoliber_NebulaGrid::column/badge.phtml';
    }

    protected function toViewData(array $column, string $key, array $item): array
    {
        $value = $item[$key] ?? null;
        $option = $column['options'][(string) $value] ?? null;

        if ($option === null) {
            return [
                'column' => $column,
                'key' => $key,
                'item' => $item,
                'value' => $value,
                'has_option' => false,
            ];
        }

        $class = (string) ($option['class'] ?? 'neutral');

        return [
            'column' => $column,
            'key' => $key,
            'item' => $item,
            'value' => $value,
            'has_option' => true,
            'label' => (string) ($option['label'] ?? (string) $value),
            'tw_class' => self::BADGE_CLASS_MAP[$class] ?? $class,
        ];
    }
}
