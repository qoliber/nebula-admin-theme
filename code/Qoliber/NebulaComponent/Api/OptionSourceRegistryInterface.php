<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/**
 * Alias → \Magento\Framework\Data\OptionSourceInterface registry used by form
 * fields that declare `"optionsSource": "<alias>"`.
 *
 * @api
 */
interface OptionSourceRegistryInterface
{
    public function register(string $alias, string $className): void;

    /**
     * @throws \Qoliber\NebulaComponent\Exception\UnknownAliasException
     */
    public function get(string $alias): \Magento\Framework\Data\OptionSourceInterface;

    public function has(string $alias): bool;

    /**
     * @return array<string, string> alias → classname map
     */
    public function all(): array;
}
