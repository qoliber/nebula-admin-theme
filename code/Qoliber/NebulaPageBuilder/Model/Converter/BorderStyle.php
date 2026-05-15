<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\Converter;

use Qoliber\NebulaPageBuilder\Api\ConverterInterface;

class BorderStyle implements ConverterInterface
{
    public function toMaster(mixed $value, array $data = []): string
    {
        if (empty($value) || $value === 'none') {
            return '';
        }

        return (string) $value;
    }

    public function fromMaster(string $value, array $data = []): mixed
    {
        return $value ?: 'none';
    }
}
