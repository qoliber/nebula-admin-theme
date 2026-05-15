<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

interface SnippetResolverInterface
{
    /**
     * @param string $id
     * @return array
     */
    public function resolve(string $id): array;

    /**
     * @param array $definition
     * @return array
     */
    public function resolveLayout(array $definition): array;
}
