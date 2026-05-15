<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/**
 * Alias → \Qoliber\NebulaComponent\Api\FormSaveHandlerInterface registry.
 *
 * @api
 */
interface SaveHandlerRegistryInterface
{
    public function register(string $alias, string $className): void;

    /**
     * @throws \Qoliber\NebulaComponent\Exception\UnknownAliasException
     */
    public function get(string $alias): \Qoliber\NebulaComponent\Api\FormSaveHandlerInterface;

    public function has(string $alias): bool;

    /**
     * @return array<string, string> alias → classname map
     */
    public function all(): array;
}
