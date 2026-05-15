<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\Widget;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Widget\Model\Widget;
use Qoliber\NebulaTheme\Model\Registry\WidgetContainerFallbackRegistry;

/**
 * Widget Layout-Updates catalog: per-widget-type supported containers
 * and per-container valid templates.
 *
 * Read by the wizard phtml to inline two JSON maps:
 *   - containersByType[typeCode] = list<containerName>
 *   - containerTemplatesByType[typeCode][containerName] = list<{value, label}>
 *
 * Both maps cascade in the Layout Updates section. The Container chooser
 * is also augmented at runtime by the Blocks AJAX endpoint, so this
 * catalog is the synchronous starting state + the post-AJAX fallback.
 *
 * Widgets with no <containers> block in widget.xml fall back to the
 * curated list held by WidgetContainerFallbackRegistry — extensible via
 * di.xml without monkey-patching.
 */
class ContainerCatalog implements ArgumentInterface
{
    public function __construct(
        private readonly Widget $widget,
        private readonly WidgetContainerFallbackRegistry $fallback,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getContainersFor(string $typeCode): array
    {
        $config = $this->widget->getConfigAsObject($typeCode);
        $raw    = $config->getData('supported_containers');
        if (!is_array($raw) || $raw === []) {
            return $this->fallback->all();
        }
        $names = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = (string) ($entry['container_name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return $names !== [] ? $names : $this->fallback->all();
    }

    /**
     * @param list<array{value: string, label: string}> $types
     * @return array<string, list<string>>
     */
    public function getAllContainers(array $types): array
    {
        $map = [];
        foreach ($types as $type) {
            $map[$type['value']] = $this->getContainersFor($type['value']);
        }
        return $map;
    }

    /**
     * Per-container template options for the given widget type.
     *
     * Mapping in widget.xml:
     *   <container name="content">
     *       <template name="grid" value="default" />
     *   </container>
     *   <parameters>
     *       <parameter name="template" xsi:type="select">
     *           <options>
     *               <option name="default" value="product/widget/new_grid.phtml">
     *                   <label>Grid</label>
     *               </option>
     *           </options>
     *       </parameter>
     *   </parameters>
     *
     * The container's <template value="X"> references an <option name="X">
     * in the widget's template parameter, whose value attribute is the
     * actual template path. We need the raw (pre-prepareDropDownValues)
     * widget config to keep the option-name keying intact — `getWidgets()`
     * returns it.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function getContainerTemplatesFor(string $typeCode): array
    {
        $raw = $this->findRawWidget($typeCode);
        if ($raw === null) {
            return [];
        }

        $containers = is_array($raw['supported_containers'] ?? null) ? $raw['supported_containers'] : [];
        $templateValues = is_array($raw['parameters']['template']['values'] ?? null)
            ? $raw['parameters']['template']['values']
            : [];

        if ($containers === []) {
            // No <containers> declared: every fallback container gets every
            // template option — we can't filter without per-container metadata.
            $allTemplates = $this->flattenTemplateValues($templateValues);
            if ($allTemplates === []) {
                return [];
            }
            $result = [];
            foreach ($this->fallback->all() as $name) {
                $result[$name] = $allTemplates;
            }
            return $result;
        }

        $result = [];
        foreach ($containers as $container) {
            if (!is_array($container)) {
                continue;
            }
            $containerName = (string) ($container['container_name'] ?? '');
            if ($containerName === '') {
                continue;
            }
            $result[$containerName] = $this->lookupContainerTemplates(
                is_array($container['template'] ?? null) ? $container['template'] : [],
                $templateValues,
            );
        }
        return $result;
    }

    /**
     * @param list<array{value: string, label: string}> $types
     * @return array<string, array<string, list<array{value: string, label: string}>>>
     */
    public function getAllContainerTemplates(array $types): array
    {
        $map = [];
        foreach ($types as $type) {
            $map[$type['value']] = $this->getContainerTemplatesFor($type['value']);
        }
        return $map;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRawWidget(string $typeCode): ?array
    {
        foreach ($this->widget->getWidgets() as $entry) {
            if (is_array($entry) && (string) ($entry['@']['type'] ?? '') === $typeCode) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @param array<int|string, mixed> $templateMap container's `template` array — keyed
     *        by template-name with the widget-template option-name as the value.
     * @param array<string, mixed> $templateValues raw widget template-parameter `values`,
     *        keyed by option name.
     * @return list<array{value: string, label: string}>
     */
    private function lookupContainerTemplates(array $templateMap, array $templateValues): array
    {
        $rows = [];
        foreach ($templateMap as $optionName) {
            $optionName = (string) $optionName;
            if (!isset($templateValues[$optionName]) || !is_array($templateValues[$optionName])) {
                continue;
            }
            $rows[] = [
                'value' => (string) ($templateValues[$optionName]['value'] ?? ''),
                'label' => (string) ($templateValues[$optionName]['label'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * @param array<string, array{label?: mixed, value?: mixed}> $templateValues
     * @return list<array{value: string, label: string}>
     */
    private function flattenTemplateValues(array $templateValues): array
    {
        $rows = [];
        foreach ($templateValues as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $rows[] = [
                'value' => (string) ($entry['value'] ?? ''),
                'label' => (string) ($entry['label'] ?? ''),
            ];
        }
        return $rows;
    }
}
