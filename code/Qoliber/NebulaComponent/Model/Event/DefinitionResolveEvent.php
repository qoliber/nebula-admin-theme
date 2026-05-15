<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Event;

/**
 * Typed payload for the `nebula_definition_resolved_before` and
 * `nebula_definition_resolved_after` events.
 *
 * Observers can mutate the definition via {@see self::setDefinition()}. The
 * mutation propagates to later observers and, for `_before`, to the resolver
 * itself; for `_after`, to the consumer block.
 *
 * @api
 */
class DefinitionResolveEvent
{
    /**
     * @param string $type one of `grid`, `form`, `snippet`, `content_type`
     * @param string $id definition id (matches JSON filename stem)
     * @param array<string, mixed> $definition merged definition as seen at the hook site
     */
    public function __construct(
        private readonly string $type,
        private readonly string $id,
        private array $definition
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        return $this->definition;
    }

    /**
     * @param array<string, mixed> $definition
     */
    public function setDefinition(array $definition): void
    {
        $this->definition = $definition;
    }
}
