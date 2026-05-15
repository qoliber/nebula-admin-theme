<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/**
 * Alias → option-source registry used by grid columns that declare
 * `"filterOptionsSource": "<alias>"`.
 *
 * Implementations typically resolve to \Magento\Framework\Data\OptionSourceInterface
 * but the registry is permissive: any object exposing `toOptionArray()` is accepted
 * so legacy Magento "system stores" sources remain usable without wrapping.
 *
 * @api
 */
interface FilterOptionSourceRegistryInterface
{
    public function register(string $alias, string $className): void;

    /**
     * @throws \Qoliber\NebulaComponent\Exception\UnknownAliasException
     */
    public function get(string $alias): object;

    public function has(string $alias): bool;

    /**
     * @return array<string, string> alias → classname map
     */
    public function all(): array;
}
