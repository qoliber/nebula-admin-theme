<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Eav;

/**
 * Walks a form-definition layout tree collecting attribute codes and group
 * codes that are explicitly rendered. Used by {@see AttributeGroupRenderer}
 * to decide what's left over to auto-render.
 */
class LayoutAttributeCollector
{
    /**
     * @param array<int, array<string, mixed>> $layout
     * @return array<int, string>
     */
    public function collectFieldCodes(array $layout): array
    {
        $codes = [];
        $this->walk($layout, 'fields', $codes);

        return $codes;
    }

    /**
     * @param array<int, array<string, mixed>> $layout
     * @return array<int, string>
     */
    public function collectGroupCodes(array $layout): array
    {
        $codes = [];
        $this->walk($layout, 'groups', $codes);

        return $codes;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, string> $codes
     */
    private function walk(array $nodes, string $key, array &$codes): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (!empty($node[$key]) && is_array($node[$key])) {
                foreach ($node[$key] as $code) {
                    if (is_string($code)) {
                        $codes[] = $code;
                    }
                }
            }

            if (!empty($node['children']) && is_array($node['children'])) {
                $this->walk($node['children'], $key, $codes);
            }
        }
    }
}
