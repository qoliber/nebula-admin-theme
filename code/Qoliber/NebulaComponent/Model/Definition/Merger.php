<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Definition;

/**
 * Deep-merge engine for Nebula JSON definitions.
 *
 * Merge rules:
 * - New key → added
 * - Existing key (associative array) → deep-merged recursively
 * - Existing key (sequential array WITHOUT id keys) → overlay replaces base
 * - Existing key (sequential array WITH id keys) → ID-based merge:
 *   - Matched IDs → deep-merged
 *   - New IDs → appended
 *   - IDs with "$remove": true → removed
 * - Scalar values → overlay wins
 * - "$remove": true → removes the key
 * - "position" values control final sort order
 */
class Merger
{
    public function __construct(
        private readonly \Psr\Log\LoggerInterface $logger,
        private readonly bool $verbose = false,
    ) {
    }
    /**
     * Deep-merge an array of definition arrays in order.
     *
     * @param array<int, array> $definitions
     * @return array
     */
    public function merge(array $definitions): array
    {
        $result = [];

        foreach ($definitions as $definition) {
            $result = $this->deepMerge($result, $definition);
        }

        $result = $this->sortByPosition($result);

        return $result;
    }

    /** @param array $base @param array $overlay @return array */
    private function deepMerge(array $base, array $overlay, string $parentKey = ''): array
    {
        foreach ($overlay as $key => $value) {
            $fullKey = $parentKey ? "$parentKey.$key" : $key;

            // Handle $remove
            if (is_array($value) && !empty($value['$remove'])) {
                if ($this->verbose) {
                    $this->logger->debug('[Nebula Merger] Removed key: ' . $fullKey);
                }
                unset($base[$key]);
                continue;
            }

            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                if ($this->isAssociative($value) && $this->isAssociative($base[$key])) {
                    // Both associative → deep merge
                    $base[$key] = $this->deepMerge($base[$key], $value, $fullKey);
                } elseif ($this->isIdBasedArray($base[$key]) || $this->isIdBasedArray($value)) {
                    // Sequential arrays with id keys → ID-based merge
                    $base[$key] = $this->mergeById($base[$key], $value, $fullKey);
                } else {
                    // Plain sequential arrays → overlay replaces
                    if ($this->verbose) {
                        $this->logger->debug('[Nebula Merger] Overlay replaces sequential array: ' . $fullKey);
                    }
                    $base[$key] = $value;
                }
            } else {
                if ($this->verbose) {
                    $this->logger->debug('[Nebula Merger] Overlay applied to: ' . $fullKey);
                }
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Check if a sequential array contains items with 'id' keys.
     */
    private function isIdBasedArray(array $array): bool
    {
        if ($this->isAssociative($array) || empty($array)) {
            return false;
        }

        foreach ($array as $item) {
            if (is_array($item) && isset($item['id'])) {
                return true;
            }
        }

        return false;
    }

    /** Merge sequential arrays by matching items via their 'id' key. */
    private function mergeById(array $base, array $overlay, string $parentKey = ''): array
    {
        // Index base items by id
        $baseById = [];
        $baseOrder = [];
        foreach ($base as $i => $item) {
            if (is_array($item) && isset($item['id'])) {
                $baseById[$item['id']] = $item;
                $baseOrder[] = $item['id'];
            } else {
                // Items without id keep their position
                $baseById['__noId_' . $i] = $item;
                $baseOrder[] = '__noId_' . $i;
            }
        }

        // Process overlay items
        foreach ($overlay as $overlayItem) {
            if (!is_array($overlayItem)) {
                continue;
            }

            $id = $overlayItem['id'] ?? null;

            if ($id === null) {
                // No ID — append as-is
                $key = '__noId_' . count($baseById);
                $baseById[$key] = $overlayItem;
                $baseOrder[] = $key;
                continue;
            }

            // Handle $remove
            if (!empty($overlayItem['$remove'])) {
                if ($this->verbose) {
                    $this->logger->debug('[Nebula Merger] Removed item with id: ' . $parentKey . '.' . $id);
                }
                unset($baseById[$id]);
                $baseOrder = array_values(array_filter($baseOrder, fn ($k) => $k !== $id));
                continue;
            }

            if (isset($baseById[$id])) {
                // Existing ID → deep merge (duplicate)
                if ($this->verbose) {
                    $this->logger->debug('[Nebula Merger] Merged duplicate id: ' . $parentKey . '.' . $id);
                }
                $baseById[$id] = $this->deepMerge($baseById[$id], $overlayItem, $parentKey . '.' . $id);
            } else {
                // New ID → append
                $baseById[$id] = $overlayItem;
                $baseOrder[] = $id;
            }
        }

        // Rebuild sequential array in order
        $result = [];
        foreach ($baseOrder as $id) {
            if (isset($baseById[$id])) {
                $result[] = $baseById[$id];
            }
        }

        // Sort by position if items have it
        $hasPosition = false;
        foreach ($result as $item) {
            if (is_array($item) && isset($item['position'])) {
                $hasPosition = true;
                break;
            }
        }

        if ($hasPosition) {
            usort($result, function ($a, $b) {
                $posA = is_array($a) && isset($a['position']) ? (int) $a['position'] : PHP_INT_MAX;
                $posB = is_array($b) && isset($b['position']) ? (int) $b['position'] : PHP_INT_MAX;
                return $posA <=> $posB;
            });
        }

        return $result;
    }

    /**
     * @param array $array
     * @return bool
     */
    private function isAssociative(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Recursively sort arrays whose items have a 'position' key.
     *
     * @param array $data
     * @return array
     */
    private function sortByPosition(array $data): array
    {
        $hasPosition = false;

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortByPosition($value);

                if (isset($value['position'])) {
                    $hasPosition = true;
                }
            }
        }

        if ($hasPosition && $this->isAssociative($data)) {
            uasort($data, function ($a, $b) {
                $posA = is_array($a) && isset($a['position']) ? (int) $a['position'] : 0;
                $posB = is_array($b) && isset($b['position']) ? (int) $b['position'] : 0;

                return $posA <=> $posB;
            });
        }

        return $data;
    }
}
