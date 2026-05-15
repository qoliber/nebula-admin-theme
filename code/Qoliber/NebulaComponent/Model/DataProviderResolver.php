<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model;

use Qoliber\NebulaComponent\Api\DataProviderInterface;
use Qoliber\NebulaComponent\Api\DataProviderRegistryInterface;

/**
 * Resolves a JSON-configured data-provider **alias** into an instance.
 *
 * Historically this class accepted an FQCN and walked through `ObjectManager`.
 * As of Phase 7, Nebula JSON must declare a registered alias (see
 * {@see \Qoliber\NebulaComponent\Api\DataProviderRegistryInterface}); FQCN
 * inputs are rejected with a typed exception to keep the surface tight.
 */
class DataProviderResolver
{
    public function __construct(
        private readonly DataProviderRegistryInterface $registry
    ) {
    }

    /**
     * Resolve a provider by alias. Returns null only when `$alias` is empty
     * (permits callers to skip the null check when a definition legitimately
     * omits the data source, e.g. layout-only snippets).
     */
    public function resolve(string $alias): ?DataProviderInterface
    {
        if ($alias === '') {
            return null;
        }

        $this->rejectFqcn($alias);

        return $this->registry->get($alias);
    }

    /**
     * Convenience: fetch data in one call. Returns an empty array shape when
     * the alias is empty, so callers don't need a null check.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function fetch(string $alias, array $config, array $params = []): array
    {
        $provider = $this->resolve($alias);

        return $provider?->getData($config, $params) ?? [];
    }

    private function rejectFqcn(string $alias): void
    {
        if (str_contains($alias, '\\')) {
            throw new \Qoliber\NebulaComponent\Exception\UnknownAliasException(
                __(
                    'Nebula data-provider FQCNs are no longer supported. '
                    . 'Register "%1" as an alias in your module\'s etc/di.xml and reference the alias in JSON.',
                    $alias
                )
            );
        }
    }
}
