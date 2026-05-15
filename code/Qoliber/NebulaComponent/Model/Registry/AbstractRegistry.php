<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Registry;

use Qoliber\NebulaComponent\Exception\UnknownAliasException;

/**
 * Shared alias → class-name store.
 *
 * Subclasses declare the expected instance type via {@see AbstractRegistry::assertInstance()}
 * so misconfigured aliases fail with a typed error at resolution time, not at call site.
 */
abstract class AbstractRegistry
{
    /** @var array<string, string> alias → class name (DI-sourced, immutable at construction) */
    private array $bindings;

    /** @var array<string, string> alias → class name (runtime-registered, overrides $bindings) */
    private array $runtime = [];

    /** @var array<string, object> resolved instance cache */
    private array $instances = [];

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array<string, string> $bindings alias → class-name map (populated by DI `<arguments>`)
     */
    public function __construct(
        private readonly \Magento\Framework\ObjectManagerInterface $objectManager,
        array $bindings = []
    ) {
        $this->bindings = $this->normalise($bindings);
    }

    public function register(string $alias, string $className): void
    {
        $alias = trim($alias);

        if ($alias === '') {
            throw new \InvalidArgumentException('Nebula registry alias cannot be empty.');
        }

        $this->runtime[$alias] = $className;
        unset($this->instances[$alias]);
    }

    public function has(string $alias): bool
    {
        return isset($this->runtime[$alias]) || isset($this->bindings[$alias]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return array_replace($this->bindings, $this->runtime);
    }

    /**
     * Resolve an alias to a shared instance. Subclasses call this from their typed `get()`.
     */
    protected function resolve(string $alias): object
    {
        if (isset($this->instances[$alias])) {
            return $this->instances[$alias];
        }

        $className = $this->runtime[$alias] ?? $this->bindings[$alias] ?? null;

        if ($className === null) {
            throw UnknownAliasException::forRegistry($this->registryLabel(), $alias, array_keys($this->all()));
        }

        $instance = $this->objectManager->get($className);
        $this->assertInstance($alias, $className, $instance);

        return $this->instances[$alias] = $instance;
    }

    /**
     * Human-readable label, used in exceptions (`"nebula %s alias …"`).
     */
    abstract protected function registryLabel(): string;

    /**
     * Validate the resolved object matches the registry's contract.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    abstract protected function assertInstance(string $alias, string $className, object $instance): void;

    /**
     * @param array<string, string> $bindings
     * @return array<string, string>
     */
    private function normalise(array $bindings): array
    {
        $clean = [];

        foreach ($bindings as $alias => $className) {
            if ($alias === '' || $className === '') {
                continue;
            }

            $clean[$alias] = $className;
        }

        return $clean;
    }
}
