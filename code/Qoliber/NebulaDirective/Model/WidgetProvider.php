<?php

declare(strict_types=1);

namespace Qoliber\NebulaDirective\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Widget\Helper\Conditions;
use Magento\Widget\Model\Widget;
use Qoliber\NebulaComponent\ViewModel\RuleEditor;
use Qoliber\NebulaTheme\Model\Registry\WidgetChooserRegistry;
use Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsPage;

class WidgetProvider
{
    public function __construct(
        private readonly Widget $widget,
        private readonly ObjectManagerInterface $objectManager,
        private readonly WidgetChooserRegistry $chooserRegistry,
        private readonly RuleEditor $ruleEditor,
        private readonly Conditions $conditionsHelper,
        private readonly Json $json,
        private readonly CmsPage $cmsPageChooser,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypes(): array
    {
        $types = [];

        foreach ($this->widget->getWidgetsArray() as $widget) {
            $type = (string) $widget['type'];
            $types[] = [
                'code'        => $type,
                'name'        => (string) $widget['name'],
                'description' => (string) $widget['description'],
                'placeholderUrl' => $this->widget->getPlaceholderImageUrl($type),
            ];
        }

        return ['types' => $types];
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getParams(string $type, array $currentValues = []): array
    {
        if ($type === '') {
            throw new LocalizedException(__('Widget type is required.'));
        }

        $widgetConfig = $this->widget->getConfigAsObject($type);
        $params = [];

        foreach ((array) $widgetConfig->getParameters() as $name => $param) {
            if (!($param instanceof DataObject)) {
                continue;
            }

            $paramType = $this->resolveParamType($param);
            $entry = [
                'name'     => $name,
                'label'    => (string) $param->getData('label'),
                'type'     => $paramType,
                'required' => (bool) $param->getData('required'),
                'default'  => $this->resolveDefaultValue($name, $param, $currentValues),
                'description' => (string) $param->getData('description'),
                'visible' => $this->resolveVisible($param),
                'depends' => $this->extractDepends($param),
                'note'     => match ($paramType) {
                    'chooser' => (string) __('Enter the ID manually.'),
                    default => '',
                },
            ];

            if ($paramType === 'select' || $paramType === 'multiselect') {
                $entry['options'] = $this->resolveOptions($param);
                $entry['multiple'] = $paramType === 'multiselect';
                $entry['type'] = 'select';
                $entry['default'] = $this->resolveSelectDefault($entry['options'], $entry['default']);
            }

            if ($paramType === 'chooser') {
                $entry['chooser'] = $this->resolveChooserAlias($param);
                $entry['selectedLabel'] = $this->resolveChooserLabel(
                    $entry['chooser'],
                    (string) $entry['default'],
                );
                $entry['note'] = $entry['chooser'] === 'generic'
                    ? (string) __('Enter the ID manually.')
                    : '';
            }

            if ($paramType === 'conditions') {
                $entry['ruleEditorConfig'] = $this->buildConditionsConfig($currentValues);
            }

            $params[] = $entry;
        }

        return ['params' => $params];
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function buildDirective(string $type, array $parameters): array
    {
        if ($type === '') {
            throw new LocalizedException(__('Widget type is required.'));
        }

        $widgetConfig = $this->widget->getConfigAsObject($type);
        $hasConditions = false;

        foreach ((array) $widgetConfig->getParameters() as $param) {
            if (!($param instanceof DataObject)) {
                continue;
            }

            $paramType = $this->resolveParamType($param);
            $isRequired = (bool) $param->getData('required');

            if ($paramType === 'conditions') {
                $hasConditions = true;
            }

            if ($paramType === 'unsupported' && $isRequired) {
                throw new LocalizedException(
                    __('The "%1" option is not supported in the Nebula widget picker yet.', (string) $param->getData('label'))
                );
            }
        }

        if ($hasConditions && empty($parameters['conditions'])) {
            throw new LocalizedException(__('Please add at least one condition to this widget.'));
        }

        $directive = $this->widget->getWidgetDeclaration($type, $parameters, true);

        return ['directive' => $directive];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getLookup(): array
    {
        $lookup = [];

        foreach ($this->getTypes()['types'] as $type) {
            $lookup[(string) $type['code']] = [
                'name' => (string) $type['name'],
                'description' => (string) $type['description'],
                'placeholderUrl' => (string) $type['placeholderUrl'],
            ];
        }

        return $lookup;
    }

    private function resolveParamType(DataObject $param): string
    {
        if ($param->getData('helper_block') instanceof DataObject) {
            return 'chooser';
        }

        $type = (string) $param->getData('type');

        if ($type !== '' && str_contains($type, '\\') && str_contains($type, 'Condition')) {
            return 'conditions';
        }

        return match ($type) {
            'select'      => 'select',
            'multiselect' => 'multiselect',
            'boolean'     => 'boolean',
            'textarea'    => 'textarea',
            default       => 'text',
        };
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function resolveOptions(DataObject $param): array
    {
        $sourceModel = (string) $param->getData('source_model');

        if ($sourceModel !== '') {
            $instance = $this->objectManager->get($sourceModel);

            if (is_object($instance) && method_exists($instance, 'toOptionArray')) {
                return $this->flattenOptionArray($instance->toOptionArray());
            }
        }

        $options = [];

        foreach ((array) $param->getData('values') as $value) {
            if (!isset($value['label'], $value['value'])) {
                continue;
            }

            $options[] = [
                'value' => (string) $value['value'],
                'label' => (string) $value['label'],
                'selected' => !empty($value['selected']),
            ];
        }

        return $options;
    }

    /**
     * @param mixed $raw
     * @return array<int, array<string, string>>
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
                    if (!is_array($child)) {
                        continue;
                    }

                    $rows[] = [
                        'value' => (string) ($child['value'] ?? ''),
                        'label' => (string) ($child['label'] ?? ''),
                        'selected' => !empty($child['selected']),
                    ];
                }

                continue;
            }

            $rows[] = [
                'value' => (string) ($entry['value'] ?? ''),
                'label' => (string) ($entry['label'] ?? ''),
                'selected' => !empty($entry['selected']),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $options
     */
    private function resolveSelectDefault(array $options, string $default): string
    {
        if ($default !== '') {
            return $default;
        }

        foreach ($options as $option) {
            if (!empty($option['selected'])) {
                return (string) ($option['value'] ?? '');
            }
        }

        if (count($options) === 1) {
            return (string) ($options[0]['value'] ?? '');
        }

        return $default;
    }

    /**
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

            foreach ($values as $value) {
                $out[] = [
                    'param' => (string) $dependsOn,
                    'value' => (string) $value,
                ];
            }
        }

        return $out;
    }

    private function resolveChooserAlias(DataObject $param): string
    {
        $helper = $param->getData('helper_block');

        if (!$helper instanceof DataObject) {
            return 'generic';
        }

        $helperType = (string) $helper->getType();
        $alias = $this->chooserRegistry->aliasForHelperBlock($helperType);

        if ($alias !== 'generic') {
            return $alias;
        }

        return match ($helperType) {
            'Magento\Cms\Block\Adminhtml\Page\Widget\Chooser' => 'cms_page',
            default => 'generic',
        };
    }

    private function resolveVisible(DataObject $param): bool
    {
        $rawVisible = $param->getData('visible');

        return $rawVisible !== '0' && $rawVisible !== 0 && $rawVisible !== false;
    }

    private function resolveDefaultValue(string $name, DataObject $param, array $currentValues): string
    {
        $value = $currentValues[$name] ?? $param->getData('value') ?? '';

        if (is_array($value)) {
            return implode(',', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConditionsConfig(array $currentValues): array
    {
        $conditions = [];

        if (isset($currentValues['conditions']) && is_array($currentValues['conditions'])) {
            $conditions = $currentValues['conditions'];
        } elseif (!empty($currentValues['conditions_encoded']) && is_string($currentValues['conditions_encoded'])) {
            $conditions = $this->conditionsHelper->decode($currentValues['conditions_encoded']);
        }

        return $this->ruleEditor->buildConfig(
            ['conditions_serialized' => $this->json->serialize($conditions)],
            'catalog',
            'parameters'
        );
    }

    private function resolveChooserLabel(string $alias, string $value): string
    {
        if ($alias === 'generic' || $value === '') {
            return '';
        }

        $row = null;

        if ($this->chooserRegistry->has($alias)) {
            $row = $this->chooserRegistry->resolveViewModel($alias)->getByValue($value);
        } elseif ($alias === 'cms_page') {
            $row = $this->cmsPageChooser->getByValue($value);
        }

        if (!is_array($row)) {
            return '';
        }

        return $this->formatChooserLabel($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatChooserLabel(array $row): string
    {
        $label = trim((string) ($row['label'] ?? $row['title'] ?? ''));

        if ($label !== '') {
            $secondary = trim((string) ($row['identifier'] ?? $row['sku'] ?? ''));

            if ($secondary !== '') {
                return $label . ' (' . $secondary . ')';
            }

            return $label;
        }

        return trim((string) ($row['value'] ?? ''));
    }
}
