<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

interface DefinitionResolverInterface
{
    /**
     * @param string $type
     * @param string $id
     * @return array
     */
    public function resolve(string $type, string $id): array;

    /**
     * @param string|null $type
     * @param string|null $id
     * @return void
     */
    public function clearCache(?string $type = null, ?string $id = null): void;
}
