<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/**
 * Alias → \Qoliber\NebulaComponent\Api\DataProviderInterface registry.
 *
 * Nebula JSON definitions reference data providers by a short, stable alias
 * (e.g. `"provider": "catalog.product.grid"`). This registry maps aliases to
 * concrete classnames via DI, keeping FQCNs out of JSON and out of consumer code.
 *
 * @api
 */
interface DataProviderRegistryInterface
{
    /**
     * Register or override a data-provider alias at runtime. Aliases registered
     * here take precedence over DI-injected mappings.
     */
    public function register(string $alias, string $className): void;

    /**
     * Return the data-provider instance bound to an alias.
     *
     * @throws \Qoliber\NebulaComponent\Exception\UnknownAliasException when the alias is unknown
     */
    public function get(string $alias): \Qoliber\NebulaComponent\Api\DataProviderInterface;

    /**
     * Check whether an alias is registered.
     */
    public function has(string $alias): bool;

    /**
     * @return array<string, string> alias → classname map
     */
    public function all(): array;
}
