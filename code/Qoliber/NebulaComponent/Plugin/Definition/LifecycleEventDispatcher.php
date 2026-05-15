<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Plugin\Definition;

use Qoliber\NebulaComponent\Api\DefinitionResolverInterface;
use Qoliber\NebulaComponent\Model\Event\DefinitionResolveEvent;

/**
 * Fires typed lifecycle events around every definition resolution.
 *
 * Events:
 *   - `nebula_definition_resolved_before` — observers can short-circuit or
 *     preload; receives a {@see DefinitionResolveEvent} with the current
 *     best-known definition (empty array on the first hop).
 *   - `nebula_definition_resolved_after` — observers can enrich the merged
 *     definition before it reaches grids/forms; receives the same event
 *     populated with the resolved payload.
 *
 * Observers that mutate the event's definition via
 * {@see DefinitionResolveEvent::setDefinition()} see their changes propagate
 * to the caller.
 */
class LifecycleEventDispatcher
{
    public function __construct(
        private readonly \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function beforeResolve(DefinitionResolverInterface $subject, string $type, string $id): array
    {
        $event = new DefinitionResolveEvent($type, $id, []);
        $this->eventManager->dispatch('nebula_definition_resolved_before', ['event' => $event]);

        return [$type, $id];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterResolve(DefinitionResolverInterface $subject, array $result, string $type, string $id): array
    {
        $event = new DefinitionResolveEvent($type, $id, $result);
        $this->eventManager->dispatch('nebula_definition_resolved_after', ['event' => $event]);

        return $event->getDefinition();
    }
}
