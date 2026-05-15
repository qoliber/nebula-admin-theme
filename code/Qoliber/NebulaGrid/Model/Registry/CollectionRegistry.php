<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Model\Registry;

use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaGrid\Api\CollectionRegistryInterface;

/**
 * Default {@see CollectionRegistryInterface} implementation. Bindings are
 * provided via DI `<arguments>` (see NebulaTheme/NebulaStore etc/di.xml).
 *
 * @api
 */
class CollectionRegistry implements CollectionRegistryInterface
{
    /** @var array<string, string> alias → class name (DI-sourced, immutable at construction) */
    private array $bindings;

    /** @var array<string, string> alias → class name (runtime-registered, overrides $bindings) */
    private array $runtime = [];

    /**
     * @param array<string, string> $bindings alias → classname map (populated by DI `<arguments>`)
     */
    public function __construct(array $bindings = [])
    {
        $this->bindings = $this->normalise($bindings);
    }

    public function register(string $alias, string $className): void
    {
        $alias = trim($alias);

        if ($alias === '') {
            throw new \InvalidArgumentException('Nebula collection registry alias cannot be empty.');
        }

        $this->runtime[$alias] = $className;
    }

    public function resolve(string $alias): string
    {
        $className = $this->runtime[$alias] ?? $this->bindings[$alias] ?? null;

        if ($className === null) {
            throw UnknownAliasException::forRegistry('collection', $alias, array_keys($this->all()));
        }

        return $className;
    }

    public function has(string $alias): bool
    {
        return isset($this->runtime[$alias]) || isset($this->bindings[$alias]);
    }

    public function all(): array
    {
        return array_replace($this->bindings, $this->runtime);
    }

    /**
     * @param array<string, string> $bindings
     * @return array<string, string>
     */
    private function normalise(array $bindings): array
    {
        $out = [];
        foreach ($bindings as $alias => $className) {
            $alias = trim((string) $alias);
            $className = trim((string) $className);
            if ($alias === '' || $className === '') {
                continue;
            }
            $out[$alias] = $className;
        }
        return $out;
    }
}
