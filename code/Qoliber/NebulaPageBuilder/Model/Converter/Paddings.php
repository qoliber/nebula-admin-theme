<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\Converter;

use Qoliber\NebulaPageBuilder\Api\ConverterInterface;

class Paddings implements ConverterInterface
{
    public function toMaster(mixed $value, array $data = []): string
    {
        if (!is_array($value)) {
            return '';
        }

        $top = $value['padding']['top'] ?? '';
        $right = $value['padding']['right'] ?? '';
        $bottom = $value['padding']['bottom'] ?? '';
        $left = $value['padding']['left'] ?? '';

        return implode(' ', array_filter([
            $this->toPx($top),
            $this->toPx($right),
            $this->toPx($bottom),
            $this->toPx($left),
        ]));
    }

    public function fromMaster(string $value, array $data = []): mixed
    {
        $parts = preg_split('/\s+/', trim($value));

        return [
            'padding' => [
                'top' => $parts[0] ?? '',
                'right' => $parts[1] ?? ($parts[0] ?? ''),
                'bottom' => $parts[2] ?? ($parts[0] ?? ''),
                'left' => $parts[3] ?? ($parts[1] ?? ($parts[0] ?? '')),
            ],
        ];
    }

    private function toPx(string $value): string
    {
        if ($value === '' || $value === '0') {
            return '';
        }

        if (is_numeric($value)) {
            return $value . 'px';
        }

        return $value;
    }
}
