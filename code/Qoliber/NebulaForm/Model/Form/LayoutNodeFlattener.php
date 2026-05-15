<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Form;

/**
 * Flattens a SimpleForm's `layout` tree (rows, columns, sections, fields,
 * snippets) into the same marker+section node stream that
 * {@see FieldsetLayoutBuilder} produces from fieldsets.
 *
 * Extracted from {@see \Qoliber\NebulaForm\Block\Form}.
 */
class LayoutNodeFlattener
{
    /**
     * @param array<int, array<string, mixed>>          $layout
     * @param array<string, array<string, mixed>>       $allFields keyed by field key
     * @return array<int, array<string, mixed>>
     */
    public function flatten(array $layout, array $allFields): array
    {
        $flatNodes = [];

        foreach ($layout as $node) {
            if (is_array($node)) {
                $this->flattenNode($node, $allFields, $flatNodes);
            }
        }

        return $flatNodes;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array<string, mixed>> $allFields
     * @param array<int, array<string, mixed>> $flatNodes
     */
    private function flattenNode(array $node, array $allFields, array &$flatNodes): void
    {
        $type = $node['type'] ?? '';

        if ($type === 'row') {
            $columns = array_values(array_filter(
                $node['children'] ?? [],
                static fn (mixed $child): bool => is_array($child) && (($child['type'] ?? '') === 'column')
            ));
            $columnCount = max(1, count($columns));

            $flatNodes[] = ['__marker' => 'row_start', '__cols' => $columnCount];

            if ($columns !== []) {
                foreach ($columns as $index => $column) {
                    if ($index > 0) {
                        $flatNodes[] = ['__marker' => 'col_break'];
                    }
                    $this->flattenNode($column, $allFields, $flatNodes);
                }
            } else {
                foreach ($node['children'] ?? [] as $child) {
                    if (is_array($child)) {
                        $this->flattenNode($child, $allFields, $flatNodes);
                    }
                }
            }

            $flatNodes[] = ['__marker' => 'row_end'];

            return;
        }

        if ($type === 'column') {
            foreach ($node['children'] ?? [] as $child) {
                if (is_array($child)) {
                    $this->flattenNode($child, $allFields, $flatNodes);
                }
            }

            return;
        }

        if ($type !== 'section') {
            return;
        }

        $flatNodes[] = $this->buildSection($node, $allFields);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array<string, mixed>> $allFields
     * @return array<string, mixed>
     */
    private function buildSection(array $node, array $allFields): array
    {
        $fieldKeys = [];
        $sectionRenderers = [];

        if (!empty($node['renderer'])) {
            $sectionRenderers[] = (string) $node['renderer'];
        }

        if (!empty($node['fields']) && is_array($node['fields'])) {
            $fieldKeys = array_values(array_filter($node['fields'], 'is_string'));
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $childType = $child['type'] ?? '';

                if ($childType === 'fields' && !empty($child['fields']) && is_array($child['fields'])) {
                    foreach ($child['fields'] as $fieldKey) {
                        if (is_string($fieldKey)) {
                            $fieldKeys[] = $fieldKey;
                        }
                    }
                } elseif ($childType === 'snippet' && !empty($child['renderer'])) {
                    $sectionRenderers[] = (string) $child['renderer'];
                }
            }
        }

        return [
            'id' => $node['id'] ?? null,
            'label' => $node['label'] ?? '',
            'open' => $node['open'] ?? true,
            'renderers' => $sectionRenderers,
            'fields' => $this->pickFields($allFields, $fieldKeys),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $allFields
     * @param array<int, string> $fieldKeys
     * @return array<string, array<string, mixed>>
     */
    private function pickFields(array $allFields, array $fieldKeys): array
    {
        $picked = [];

        foreach ($fieldKeys as $fieldKey) {
            if (!isset($allFields[$fieldKey])) {
                continue;
            }

            $picked[$fieldKey] = $allFields[$fieldKey];
        }

        uasort($picked, static function (array $a, array $b): int {
            return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
        });

        return $picked;
    }
}
