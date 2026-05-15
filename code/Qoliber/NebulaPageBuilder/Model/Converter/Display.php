<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\Converter;

use Qoliber\NebulaPageBuilder\Api\ConverterInterface;

class Display implements ConverterInterface
{
    public function toMaster(mixed $value, array $data = []): string
    {
        if ($value === 'hide' || $value === false || $value === 'none') {
            return 'none';
        }

        return '';
    }

    public function fromMaster(string $value, array $data = []): mixed
    {
        return $value === 'none' ? 'none' : '';
    }
}
