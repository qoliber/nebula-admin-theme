<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Api;

interface ConverterInterface
{
    /**
     * Convert data field value to CSS style value for master format.
     *
     * @param mixed $value
     * @param array<string, mixed> $data
     * @return string
     */
    public function toMaster(mixed $value, array $data = []): string;

    /**
     * Convert CSS style value from master format back to data field value.
     *
     * @param string $value
     * @param array<string, mixed> $data
     * @return mixed
     */
    public function fromMaster(string $value, array $data = []): mixed;
}
