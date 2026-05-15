<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Form;

use Magento\Eav\Model\Config as EavConfig;

/**
 * Resolves the set of field keys that a given form definition is allowed to
 * persist. Used by Form\Save to filter the request payload before handing it
 * to the entity — without this, the controller would addData() every key
 * the client sent, opening arbitrary mass-assignment.
 *
 * Resolution rules:
 *  - Walk `layout` (tree) and collect every `fields` array entry.
 *  - Add `overrides.fields` keys, skipping entries flagged `$remove`.
 *  - For EAV forms, also include every attribute code declared for the
 *    entity type (via EavConfig). This is broader than ideal but still
 *    bounded: only known EAV attributes for that entity type are allowed,
 *    not arbitrary keys.
 *  - The identifier param key (`identifierField` or default `entity_id`) is
 *    NOT auto-included — entity id comes from the URL, not the body.
 */
class AllowedFieldsCollector
{
    public function __construct(
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<int, string>
     */
    public function collect(array $definition): array
    {
        $allowed = [];

        $this->walkLayout($definition['layout'] ?? [], $allowed);

        foreach ((array) ($definition['overrides']['fields'] ?? []) as $field => $config) {
            if (is_array($config) && !empty($config['$remove'])) {
                continue;
            }
            $allowed[(string) $field] = true;
        }

        if (($definition['type'] ?? '') === 'eav') {
            $entityTypeCode = (string) ($definition['entity'] ?? '');
            if ($entityTypeCode !== '') {
                foreach ($this->eavAttributeCodes($entityTypeCode) as $code) {
                    $allowed[$code] = true;
                }
            }
        }

        return array_keys($allowed);
    }

    /**
     * @param array<int, mixed> $nodes
     * @param array<string, true> $allowed
     */
    private function walkLayout(array $nodes, array &$allowed): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            foreach ((array) ($node['fields'] ?? []) as $field) {
                if (is_string($field)) {
                    $allowed[$field] = true;
                }
            }

            if (!empty($node['children']) && is_array($node['children'])) {
                $this->walkLayout($node['children'], $allowed);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function eavAttributeCodes(string $entityTypeCode): array
    {
        try {
            $attributes = $this->eavConfig->getEntityAttributes($entityTypeCode);
        } catch (\Throwable) {
            return [];
        }

        $codes = [];
        foreach ($attributes as $attribute) {
            $code = $attribute->getAttributeCode();
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
