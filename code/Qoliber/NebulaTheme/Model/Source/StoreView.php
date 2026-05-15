<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\System\Store as SystemStore;

/**
 * Tree-shaped option source for the website / store-group / store-view ladder.
 *
 * Vendor's SystemStore::getStoreValuesForForm() returns a NESTED structure
 * where a group entry's 'value' is the array of its child store views.
 * This source walks that structure into the Nebula tree-row shape:
 *
 *   ['value', 'label', 'depth', 'group']
 *
 *   - depth: 0 = website header / 'All Store Views' leaf
 *   - depth: 1 = group header (or leaf when compacted)
 *   - depth: 2 = store-view leaf
 *
 * Single-group websites are compacted: the lone group header is dropped and
 * its stores lift from depth 2 to depth 1 — reads cleaner in the UI.
 *
 * Registered as alias 'nebula.store.view' in NebulaTheme/etc/adminhtml/di.xml.
 */
class StoreView implements OptionSourceInterface, ArgumentInterface
{
    private const NBSP = "\xC2\xA0";

    public function __construct(
        private readonly SystemStore $systemStore,
    ) {
    }

    /**
     * @return list<array{value: ?string, label: string, depth: int, group?: bool, all?: bool}>
     */
    public function toOptionArray(): array
    {
        $rows = [];

        foreach ($this->systemStore->getStoreValuesForForm(false, true) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rawLabel = (string) ($entry['label'] ?? '');
            $value    = $entry['value'] ?? null;
            $clean    = trim(str_replace(self::NBSP, '', $rawLabel));

            if ($value === 0 || $value === '0') {
                // The "All Store Views" sentinel — toggling it is mutually
                // exclusive with any individual store-view selection. The
                // renderer reads `all: true` to enforce the rule.
                $rows[] = ['value' => '0', 'label' => $clean, 'depth' => 0, 'all' => true];
                continue;
            }

            if (is_array($value)) {
                if ($value === []) {
                    // Website header.
                    $rows[] = ['value' => null, 'label' => $clean, 'depth' => 0, 'group' => true];
                    continue;
                }

                // Group header + nested store views.
                $rows[] = ['value' => null, 'label' => $clean, 'depth' => 1, 'group' => true];
                foreach ($value as $child) {
                    if (!is_array($child)) {
                        continue;
                    }
                    $childVal = $child['value'] ?? null;
                    if ($childVal === null || is_array($childVal)) {
                        continue;
                    }
                    $rows[] = [
                        'value' => (string) $childVal,
                        'label' => trim(str_replace(self::NBSP, '', (string) ($child['label'] ?? ''))),
                        'depth' => 2,
                    ];
                }
            }
        }

        return $this->compactSingleGroup($rows);
    }

    /**
     * If the whole tree contains exactly one group header (depth 1), drop it
     * and lift its child store views from depth 2 to depth 1.
     *
     * @param list<array{value: ?string, label: string, depth: int, group?: bool, all?: bool}> $rows
     * @return list<array{value: ?string, label: string, depth: int, group?: bool, all?: bool}>
     */
    private function compactSingleGroup(array $rows): array
    {
        $groupCount = 0;
        foreach ($rows as $row) {
            if (($row['group'] ?? false) && $row['depth'] === 1) {
                $groupCount++;
            }
        }

        if ($groupCount !== 1) {
            return $rows;
        }

        $compact = [];
        foreach ($rows as $row) {
            if (($row['group'] ?? false) && $row['depth'] === 1) {
                continue;
            }
            if (!($row['group'] ?? false) && $row['depth'] === 2) {
                $row['depth'] = 1;
            }
            $compact[] = $row;
        }

        return $compact;
    }
}
