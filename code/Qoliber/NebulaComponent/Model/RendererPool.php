<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model;

use Qoliber\NebulaComponent\Api\ColumnRendererInterface;
use Qoliber\NebulaComponent\Api\FieldRendererInterface;

class RendererPool
{
    /**
     * @param \Qoliber\NebulaComponent\Api\FieldRendererInterface[]  $fieldRenderers keyed by field type
     * @param \Qoliber\NebulaComponent\Api\ColumnRendererInterface[] $columnRenderers keyed by column type
     * @param \Qoliber\NebulaComponent\Api\ColumnRendererInterface|null $defaultColumnRenderer fallback when type is unknown
     */
    public function __construct(
        private readonly array $fieldRenderers = [],
        private readonly array $columnRenderers = [],
        private readonly ?ColumnRendererInterface $defaultColumnRenderer = null
    ) {
    }

    public function getFieldComponent(string $type): string
    {
        if (isset($this->fieldRenderers[$type])) {
            return $this->fieldRenderers[$type]->getComponentName();
        }

        return 'nebulaField_' . $type;
    }

    public function getColumnComponent(string $type): string
    {
        if (isset($this->columnRenderers[$type])) {
            return $this->columnRenderers[$type]->getComponentName();
        }

        return 'nebulaColumn_' . $type;
    }

    public function hasColumnRenderer(string $type): bool
    {
        return isset($this->columnRenderers[$type]);
    }

    /**
     * Resolve a column renderer for the given type, falling back to the
     * default renderer (text) when the type is unknown.
     */
    public function getColumnRenderer(string $type): ColumnRendererInterface
    {
        if (isset($this->columnRenderers[$type])) {
            return $this->columnRenderers[$type];
        }

        if ($this->defaultColumnRenderer !== null) {
            return $this->defaultColumnRenderer;
        }

        throw new \RuntimeException(sprintf(
            'No column renderer registered for type "%s" and no default renderer configured.',
            $type
        ));
    }

    public function getFieldRenderer(string $type): ?FieldRendererInterface
    {
        return $this->fieldRenderers[$type] ?? null;
    }

    public function getFieldRendererCount(): int
    {
        return count($this->fieldRenderers);
    }

    public function getColumnRendererCount(): int
    {
        return count($this->columnRenderers);
    }
}
