<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\Converter;

use Qoliber\NebulaPageBuilder\Api\ConverterInterface;

class BackgroundImage implements ConverterInterface
{
    public function toMaster(mixed $value, array $data = []): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_array($value)) {
            $url = $value[0]['url'] ?? ($value['url'] ?? '');
        } else {
            $url = (string) $value;
        }

        return $url ? 'url(' . $url . ')' : '';
    }

    public function fromMaster(string $value, array $data = []): mixed
    {
        if (preg_match('/url\(([^)]+)\)/', $value, $matches)) {
            return [['url' => trim($matches[1], '\'"')]];
        }

        return [];
    }
}
