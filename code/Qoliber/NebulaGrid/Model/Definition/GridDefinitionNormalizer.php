<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Model\Definition;

use Psr\Log\LoggerInterface;
use Qoliber\NebulaGrid\Model\AddButtonUrlBuilder;

class GridDefinitionNormalizer
{
    private const ALLOWED_COLUMN_TYPES = ['text', 'date', 'price', 'thumbnail', 'badge', 'boolean', 'link', 'html', 'actions'];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AddButtonUrlBuilder $addButtonUrlBuilder
    ) {
    }

    public function normalize(array $definition): array
    {
        // 1. Move top-level massActions to settings.massActions if not already set
        if (isset($definition['massActions']) && is_array($definition['massActions'])) {
            if (!isset($definition['settings']['massActions']) || !is_array($definition['settings']['massActions'])) {
                $definition['settings']['massActions'] = $definition['massActions'];
            }
        }
        unset($definition['massActions']);

        // 2. Strip bridge metadata keys
        foreach (['_warning', '_generated', '_meta', 'visible', 'urlPath', 'idField', 'urlTemplate'] as $key) {
            unset($definition[$key]);
        }

        // 3. Default settings.massActions to empty array if not set or is a boolean
        if (!isset($definition['settings']['massActions']) || is_bool($definition['settings']['massActions'])) {
            $definition['settings']['massActions'] = [];
        }

        // 4. Log unknown column types
        foreach ($definition['columns'] ?? [] as $column) {
            if (isset($column['renderer']) || isset($column['template'])) {
                continue;
            }

            if (!isset($column['type'])) {
                continue;
            }

            if (!in_array($column['type'], self::ALLOWED_COLUMN_TYPES, true)) {
                $this->logger->warning(
                    sprintf(
                        'NebulaGrid: unknown column type "%s". Allowed types: %s',
                        $column['type'],
                        implode(', ', self::ALLOWED_COLUMN_TYPES)
                    )
                );
            }
        }

        $definition = $this->addButtonUrlBuilder->resolve($definition);

        return $definition;
    }
}
