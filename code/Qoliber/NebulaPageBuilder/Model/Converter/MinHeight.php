<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\Converter;

use Qoliber\NebulaPageBuilder\Api\ConverterInterface;

class MinHeight implements ConverterInterface
{
    public function toMaster(mixed $value, array $data = []): string
    {
        if (empty($value)) {
            return '';
        }

        $value = (string) $value;

        return is_numeric($value) ? $value . 'px' : $value;
    }

    public function fromMaster(string $value, array $data = []): mixed
    {
        return $value;
    }
}
