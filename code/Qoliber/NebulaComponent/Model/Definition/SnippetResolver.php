<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Definition;

use Qoliber\NebulaComponent\Api\SnippetResolverInterface;

class SnippetResolver implements SnippetResolverInterface
{
    public function __construct(
        private readonly Loader $loader,
        private readonly Merger $merger,
        private readonly \Psr\Log\LoggerInterface $logger
    ) {
    }

    /**
     * @param string $id
     * @return array
     */
    public function resolve(string $id): array
    {
        $definitions = $this->loader->load('snippet', $id);

        return $this->merger->merge($definitions);
    }

    /**
     * @param array $definition
     * @return array
     */
    public function resolveLayout(array $definition): array
    {
        if (!isset($definition['layout'])) {
            return $definition;
        }

        $layout = $definition['layout'];

        if (array_is_list($layout)) {
            $definition['layout'] = array_map(
                fn (array $node) => $this->walkLayoutTree($node, $definition),
                $layout
            );
        } else {
            $definition['layout'] = $this->walkLayoutTree($layout, $definition);
        }

        return $definition;
    }

    /**
     * @param array $node
     * @param array $definition
     * @return array
     */
    private function walkLayoutTree(array $node, array $definition): array
    {
        if (isset($node['@use'])) {
            $snippet = $this->resolve($node['@use']);
            $slots = $node['slots'] ?? [];
            unset($node['@use']);
            $node = array_merge($snippet, $node);

            if (!empty($slots)) {
                $node = $this->resolveSlots($node, $slots, $definition);
            }
        }

        if (isset($node['@ref'])) {
            $node = $this->resolveRef($node['@ref'], $definition);
        }

        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $key => $child) {
                if (is_string($child) && str_starts_with($child, '@')) {
                    $node['children'][$key] = $this->resolveRef(substr($child, 1), $definition);
                } elseif (is_array($child)) {
                    $node['children'][$key] = $this->walkLayoutTree($child, $definition);
                }
            }
        }

        return $node;
    }

    /**
     * @param string $ref
     * @param array $definition
     * @return array
     */
    private function resolveRef(string $ref, array $definition): array
    {
        $parts = explode('.', $ref, 2);

        if (count($parts) === 2 && isset($definition[$parts[0]][$parts[1]])) {
            $resolved = $definition[$parts[0]][$parts[1]];

            if (is_array($resolved)) {
                $resolved['type'] = $parts[0];
                $resolved['key'] = $parts[1];
            }

            return $resolved;
        }

        $this->logger->warning('Unresolved snippet @ref returned as placeholder.', [
            'ref' => $ref,
        ]);

        return ['@ref' => $ref];
    }

    /**
     * @param array $node
     * @param array $slots
     * @param array $definition
     * @return array
     */
    private function resolveSlots(array $node, array $slots, array $definition): array
    {
        if (!isset($node['children']) || !is_array($node['children'])) {
            return $node;
        }

        foreach ($node['children'] as $key => $child) {
            if (!is_array($child) || !isset($child['slot'])) {
                continue;
            }

            $slotName = $child['slot'];

            if (!isset($slots[$slotName])) {
                continue;
            }

            $slotContent = $slots[$slotName];
            $resolvedChildren = [];

            foreach ($slotContent as $item) {
                if (is_string($item) && str_starts_with($item, '@')) {
                    $resolvedChildren[] = $this->resolveRef(substr($item, 1), $definition);
                } elseif (is_array($item)) {
                    $resolvedChildren[] = $this->walkLayoutTree($item, $definition);
                }
            }

            $node['children'][$key]['children'] = $resolvedChildren;
        }

        return $node;
    }
}
