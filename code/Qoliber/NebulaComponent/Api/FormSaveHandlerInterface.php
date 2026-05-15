<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/**
 * Contract for Nebula form save handlers resolved by alias.
 *
 * Implementations are registered in a module's `etc/di.xml` against
 * \Qoliber\NebulaComponent\Api\SaveHandlerRegistryInterface and referenced
 * from form JSON via `"saveHandler": "<alias>"`.
 *
 * @api
 */
interface FormSaveHandlerInterface
{
    /**
     * Persist the submitted form data. Implementations are expected to validate,
     * map, persist, and return the saved entity's identifier (or a stable
     * representation thereof) so the caller can redirect or reload.
     *
     * @param array<string, mixed> $data submitted form payload (already unwrapped from field prefix)
     * @param array<string, mixed> $context caller context — typically `entityId`, `storeId`, `fieldPrefix`
     * @return array<string, mixed> mutation result — expected keys:
     *         - `entityId` (string|int|null)
     *         - `messages` (list<string>, optional informational messages)
     *         - `redirectUrl` (string|null, optional override)
     */
    public function save(array $data, array $context = []): array;
}
