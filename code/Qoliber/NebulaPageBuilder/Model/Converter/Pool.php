<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\Converter;

use Qoliber\NebulaPageBuilder\Api\ConverterInterface;

class Pool
{
    /**
     * @param array<string, \Qoliber\NebulaPageBuilder\Api\ConverterInterface> $converters
     */
    public function __construct(
        private readonly array $converters = []
    ) {
    }

    public function get(string $name): ?ConverterInterface
    {
        return $this->converters[$name] ?? null;
    }
}
