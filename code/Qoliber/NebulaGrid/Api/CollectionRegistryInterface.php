<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Api;

/**
 * Alias → collection-class registry for NebulaGrid's CollectionProvider.
 *
 * Grid JSON definitions reference collections by short, stable aliases
 * (e.g. `"collection": "cms_page.collection"`). This registry maps each
 * alias to a vendor collection FQCN via DI so JSON never carries a raw
 * class name and CollectionProvider never has to call ObjectManager with
 * an attacker-controllable string.
 *
 * The registry is a class-name resolver only — it intentionally does NOT
 * instantiate, because grid collections are stateful (filters, page
 * cursor, etc.) and each grid render needs a fresh instance. The caller
 * (CollectionProvider) instantiates after the alias resolves.
 *
 * @api
 */
interface CollectionRegistryInterface
{
    /**
     * Register or override a collection alias at runtime. Aliases registered
     * here take precedence over DI-injected mappings.
     */
    public function register(string $alias, string $className): void;

    /**
     * Return the collection FQCN bound to an alias.
     *
     * @throws \Qoliber\NebulaComponent\Exception\UnknownAliasException when the alias is unknown
     */
    public function resolve(string $alias): string;

    /**
     * Check whether an alias is registered.
     */
    public function has(string $alias): bool;

    /**
     * @return array<string, string> alias → classname map
     */
    public function all(): array;
}
