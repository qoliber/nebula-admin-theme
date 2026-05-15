<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model;

use Qoliber\NebulaComponent\Api\FilterOptionSourceRegistryInterface;
use Qoliber\NebulaComponent\Api\OptionSourceRegistryInterface;

/**
 * Resolves option-source **aliases** (as declared in grid/form JSON) into a
 * flat `value => label` map or a full option array.
 *
 * Aliases are looked up first in the registries (see
 * {@see \Qoliber\NebulaComponent\Api\OptionSourceRegistryInterface} and
 * {@see \Qoliber\NebulaComponent\Api\FilterOptionSourceRegistryInterface}).
 *
 * As of NebulaUiBridge V2 (Slice 1, 2026-04-30), an FQCN-shaped alias (any
 * string containing a backslash) that is not registered falls back to
 * \Magento\Framework\ObjectManagerInterface::create($alias). This unblocks the
 * bridge from emitting `filterOptionsSource: "Magento\\...\\Options"` for
 * components whose option-source classes have not been wrapped behind a
 * Nebula alias yet. The fallback only succeeds when the resolved object is a
 * \Magento\Framework\Data\OptionSourceInterface or exposes a `toOptionArray()`
 * method.
 *
 * Used by {@see \Qoliber\NebulaGrid\Model\ColumnLoader} for `filterOptionsSource`
 * and by {@see \Qoliber\NebulaForm\Block\Form} for `optionsSource`.
 */
class OptionSourceResolver
{
    public function __construct(
        private readonly OptionSourceRegistryInterface $optionSourceRegistry,
        private readonly FilterOptionSourceRegistryInterface $filterOptionSourceRegistry,
        private readonly \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * Resolve a filter alias to a `value => label` map. Filter sources are
     * looked up in the permissive registry first (to tolerate sources that
     * predate \Magento\Framework\Data\OptionSourceInterface), falling back to
     * the strict options registry, then to ObjectManager FQCN instantiation.
     *
     * @return array<string, string>
     */
    public function toLabelMap(string $alias): array
    {
        $source = $this->resolveFilter($alias);

        if ($source === null) {
            return [];
        }

        $map = [];

        foreach ($source->toOptionArray() as $option) {
            $value = is_array($option) ? ($option['value'] ?? '') : $option;
            $label = is_array($option) ? ($option['label'] ?? '') : $option;

            if (is_array($value) || $value === '') {
                continue;
            }

            $map[(string) $value] = (string) $label;
        }

        return $map;
    }

    /**
     * Resolve an option-source alias to a row list. Each row at minimum
     * carries `value` and `label`. Tree-shaped sources may additionally
     * include:
     *   - `depth` (int): indent level for tree rendering.
     *   - `group` (bool): if true, the row is a non-selectable header.
     *   - `disabled` (bool): leaf rendered but not toggleable.
     *
     * Rows whose `value` is `null` are kept ONLY when `group` is true; that's
     * the convention Magento uses for headers in nested option lists.
     *
     * @return list<array{value: ?string, label: string, depth?: int, group?: bool, disabled?: bool, all?: bool}>
     */
    public function toOptionArray(string $alias, string $method = 'toOptionArray'): array
    {
        $source = $this->resolveOption($alias);

        if ($source === null) {
            return [];
        }

        try {
            $options = $source->$method();
        } catch (\Throwable) {
            return [];
        }

        if (!is_iterable($options)) {
            return [];
        }

        $result = [];

        foreach ($options as $option) {
            if (!is_array($option)) {
                // Scalar source row — { 'a' => 'A' } isn't normally yielded by
                // OptionSourceInterface but tolerate it for legacy sources.
                if ($option === '' || $option === null) {
                    continue;
                }
                $result[] = ['value' => (string) $option, 'label' => (string) $option];
                continue;
            }

            $value = $option['value'] ?? null;
            $isGroup = (bool) ($option['group'] ?? false);

            if (is_array($value)) {
                // Nested vendor shape — flat consumers can't represent it.
                continue;
            }

            // Leaves require a non-empty value; group headers are allowed null.
            if (!$isGroup && ($value === null || $value === '')) {
                continue;
            }

            $row = [
                'value' => $value === null ? null : (string) $value,
                'label' => (string) ($option['label'] ?? ''),
            ];

            if (array_key_exists('depth', $option) && is_int($option['depth'])) {
                $row['depth'] = $option['depth'];
            }
            if ($isGroup) {
                $row['group'] = true;
            }
            if (!empty($option['disabled'])) {
                $row['disabled'] = true;
            }
            if (!empty($option['all'])) {
                // "Select all" sentinel — exclusive vs every other leaf.
                $row['all'] = true;
            }

            $result[] = $row;
        }

        return $result;
    }

    private function resolveOption(string $alias): ?object
    {
        if ($alias === '') {
            return null;
        }

        if ($this->optionSourceRegistry->has($alias)) {
            return $this->optionSourceRegistry->get($alias);
        }

        // Permissive fallback: some sources (e.g. System\Store) are shared between
        // option and filter-option use cases and may only be registered in the
        // permissive filter registry.
        if ($this->filterOptionSourceRegistry->has($alias)) {
            return $this->filterOptionSourceRegistry->get($alias);
        }

        // Bridge fallback (V2 Slice 1): fully-qualified class name that
        // bypasses the alias registry. Only used when the bridge emits an FQCN
        // because no alias was registered for the source.
        if (\str_contains($alias, '\\')) {
            return $this->resolveFqcn($alias);
        }

        return $this->optionSourceRegistry->get($alias); // throws UnknownAliasException
    }

    private function resolveFilter(string $alias): ?object
    {
        if ($alias === '') {
            return null;
        }

        if ($this->filterOptionSourceRegistry->has($alias)) {
            return $this->filterOptionSourceRegistry->get($alias);
        }

        if ($this->optionSourceRegistry->has($alias)) {
            return $this->optionSourceRegistry->get($alias);
        }

        if (\str_contains($alias, '\\')) {
            return $this->resolveFqcn($alias);
        }

        return $this->filterOptionSourceRegistry->get($alias); // throws UnknownAliasException
    }

    /**
     * Try to instantiate $fqcn via ObjectManager. Returns the instance only
     * when it implements \Magento\Framework\Data\OptionSourceInterface or
     * exposes a public `toOptionArray()` method. Any throwable degrades to
     * null — bridge resolution must never crash a render.
     */
    private function resolveFqcn(string $fqcn): ?object
    {
        try {
            $instance = $this->objectManager->create($fqcn);
        } catch (\Throwable) {
            return null;
        }

        if ($instance instanceof \Magento\Framework\Data\OptionSourceInterface) {
            return $instance;
        }

        if (\is_object($instance) && \method_exists($instance, 'toOptionArray')) {
            return $instance;
        }

        return null;
    }
}
