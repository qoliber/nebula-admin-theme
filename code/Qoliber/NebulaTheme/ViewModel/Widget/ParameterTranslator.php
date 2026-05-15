<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\Widget;

use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Widget\Model\Widget;
use Qoliber\NebulaTheme\Model\Registry\WidgetChooserRegistry;

/**
 * Translates one widget type's `<parameter>` elements (as read by
 * \Magento\Widget\Model\Config\Converter into prepared DataObjects)
 * into the Nebula field-config shape:
 *
 *   {name, type, label, description, required, visible, default,
 *    sortOrder, depends, options?, chooser?}
 *
 * Handles every xsi:type the audited core widget.xml files use: text,
 * select (inline + source_model), multiselect, block (chooser),
 * conditions. Helper-block FQCN → chooser-alias resolution is delegated
 * to \Qoliber\NebulaTheme\Model\Registry\WidgetChooserRegistry so
 * third-party modules can plug their own chooser snippets in via di.xml.
 */
class ParameterTranslator implements ArgumentInterface
{
    public function __construct(
        private readonly Widget $widget,
        private readonly ObjectManagerInterface $objectManager,
        private readonly WidgetChooserRegistry $chooserRegistry,
    ) {
    }

    /**
     * Parameter names that the wizard's Parameters section hides because
     * they're rendered elsewhere. Mirrors Magento's own legacy form
     * (\Magento\Widget\Block\Adminhtml\Widget\Instance\Edit\Tab\Properties::$hiddenParameters).
     *
     * `template` lives in the Layout Updates section: each layout-update
     * row picks its own template (the storefront uses the row's template,
     * not the widget_parameters one).
     */
    private const HIDDEN_PARAMETERS = ['template'];

    /**
     * @return list<array{name: string, type: string, label: string, description: string, required: bool, visible: bool, default: string, sortOrder: int, depends: list<array{param: string, value: string}>, options?: list<array{value: string, label: string}>, chooser?: string}>
     */
    public function getParameters(string $typeCode): array
    {
        $config = $this->widget->getConfigAsObject($typeCode);
        $params = $config->getData('parameters');
        if (!is_array($params)) {
            return [];
        }

        $rows = [];
        foreach ($params as $param) {
            if (!$param instanceof DataObject) {
                continue;
            }
            $row = $this->translate($param);
            if ($row === null) {
                continue;
            }
            if (in_array($row['name'], self::HIDDEN_PARAMETERS, true)) {
                continue;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Inlines `getParameters()` for every installed type. The wizard
     * phtml emits this as JSON so the Parameters section updates
     * synchronously when the user picks a different type.
     *
     * @param list<array{value: string, label: string}> $types
     * @return array<string, list<array<string, mixed>>>
     */
    public function getAllParameters(array $types): array
    {
        $map = [];
        foreach ($types as $type) {
            $map[$type['value']] = $this->getParameters($type['value']);
        }
        return $map;
    }

    /**
     * Reads from the keys Magento's converter produces:
     *
     *   - `key`          — parameter name (the outer array key, set by prepareWidgetParameters).
     *   - `type`         — normalised type: 'label' for xsi:type="block",
     *                      'select' / 'multiselect' / 'text' verbatim, or the rule-class
     *                      FQCN for xsi:type="conditions".
     *   - `helper_block` — DataObject with `getType()` returning the chooser FQCN
     *                      (set only when xsi:type="block").
     *   - `values`       — array<int, array{label,value}> for select/multiselect.
     *   - `source_model` — class FQCN that resolves options dynamically.
     *   - `label`, `description`, `value`, `required`, `visible`, `sort_order`,
     *     `depends` — top-level keys.
     *
     * NB: the converter does NOT use the `@` attribute bag for parameters
     * (unlike at the widget level). So no xsi:type lookup under `@`.
     *
     * @return array{name: string, type: string, label: string, description: string, required: bool, visible: bool, default: string, sortOrder: int, depends: list<array{param: string, value: string}>, options?: list<array{value: string, label: string}>, chooser?: string}|null
     */
    private function translate(DataObject $param): ?array
    {
        $name = (string) $param->getData('key');
        if ($name === '') {
            $name = (string) $param->getData('name');
        }
        if ($name === '') {
            return null;
        }

        $converterType = (string) $param->getData('type');
        $required      = ((string) $param->getData('required')) === '1';
        $rawVisible    = $param->getData('visible');
        // Converter writes '1'/'0' strings, or true when the visible attribute
        // is absent. Only the literal '0'/0/false counts as hidden.
        $visible = $rawVisible !== '0' && $rawVisible !== 0 && $rawVisible !== false;

        $row = [
            'name'        => $name,
            'type'        => 'text',
            'label'       => (string) $param->getData('label'),
            'description' => (string) $param->getData('description'),
            'required'    => $required,
            'visible'     => $visible,
            'default'     => (string) $param->getData('value'),
            'sortOrder'   => (int) $param->getData('sort_order'),
            'depends'     => $this->extractDepends($param),
        ];

        // xsi:type="block" → chooser, identified by helper_block presence
        // (converter rewrites the type field to 'label' for blocks).
        if ($param->getData('helper_block') instanceof DataObject) {
            $row['type']    = 'chooser';
            $row['chooser'] = $this->resolveChooserAlias($param);
            return $row;
        }

        switch ($converterType) {
            case 'select':
            case 'multiselect':
                $row['type']    = $converterType;
                $row['options'] = $this->resolveSelectOptions($param);
                break;
            case 'text':
            case '':
                $row['type'] = 'text';
                break;
            default:
                // Converter writes the conditions rule-class FQCN to the
                // type field for xsi:type="conditions".
                if (str_contains($converterType, '\\')) {
                    $row['type']    = 'chooser';
                    $row['chooser'] = 'conditions';
                } else {
                    $row['type'] = 'text';
                }
                break;
        }

        return $row;
    }

    /**
     * Converter shape for depends:
     *   ['depends' => ['show_pager' => ['value' => '1'], ...]]
     * Multi-value form (Magento 2.4.x+):
     *   ['depends' => ['show_pager' => ['values' => ['1', '2']]]]
     *
     * @return list<array{param: string, value: string}>
     */
    private function extractDepends(DataObject $param): array
    {
        $raw = $param->getData('depends');
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $dependsOn => $clause) {
            if (!is_array($clause)) {
                continue;
            }
            $values = isset($clause['values']) && is_array($clause['values'])
                ? $clause['values']
                : (isset($clause['value']) ? [$clause['value']] : []);
            foreach ($values as $v) {
                $out[] = ['param' => (string) $dependsOn, 'value' => (string) $v];
            }
        }
        return $out;
    }

    /**
     * Prefers `source_model` (Magento's OptionSourceInterface /
     * Option\ArrayInterface FQCN). Falls back to inline <options>.
     *
     * @return list<array{value: string, label: string}>
     */
    private function resolveSelectOptions(DataObject $param): array
    {
        $sourceModel = (string) $param->getData('source_model');
        if ($sourceModel !== '') {
            // Both OptionSourceInterface and the older Option\ArrayInterface
            // expose toOptionArray() — duck-type via method_exists.
            $instance = $this->objectManager->get($sourceModel);
            if (is_object($instance) && method_exists($instance, 'toOptionArray')) {
                return $this->flattenOptionArray($instance->toOptionArray());
            }
        }
        return $this->extractInlineOptions($param);
    }

    /**
     * Source-model option arrays may use nested optgroup shape; flatten
     * to a simple value/label list.
     *
     * @param mixed $raw
     * @return list<array{value: string, label: string}>
     */
    private function flattenOptionArray(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $rows = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (isset($entry['value']) && is_array($entry['value'])) {
                foreach ($entry['value'] as $child) {
                    if (is_array($child)) {
                        $rows[] = [
                            'value' => (string) ($child['value'] ?? ''),
                            'label' => (string) ($child['label'] ?? ''),
                        ];
                    }
                }
                continue;
            }
            $rows[] = [
                'value' => (string) ($entry['value'] ?? ''),
                'label' => (string) ($entry['label'] ?? ''),
            ];
        }
        return $rows;
    }

    /** @return list<array{value: string, label: string}> */
    private function extractInlineOptions(DataObject $param): array
    {
        $values = $param->getData('values');
        if (!is_array($values)) {
            return [];
        }
        $rows = [];
        foreach ($values as $entry) {
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

    private function resolveChooserAlias(DataObject $param): string
    {
        $helper = $param->getData('helper_block');
        if (!$helper instanceof DataObject) {
            return 'generic';
        }
        return $this->chooserRegistry->aliasForHelperBlock((string) $helper->getType());
    }
}
