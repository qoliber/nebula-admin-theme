<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Field;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Qoliber\NebulaForm\Block\EavForm;
use Qoliber\NebulaForm\Model\FieldNamer;

/**
 * Renders a single EAV attribute field as the Alpine-model-backed control.
 *
 * Extracted out of eav/form.phtml so the template becomes pure composition.
 * Every code path routes through Alpine `nebulaField_<type>` components —
 * NO hidden business-data inputs are emitted by this renderer.
 */
class EavFieldRenderer
{
    /** @var string */
    private const INPUT_CLASS = 'block w-full rounded-lg border border-gray-300 py-2.5 px-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-nebula-500 focus:ring-2 focus:ring-nebula-500/20 focus:outline-none disabled:bg-gray-50 disabled:text-gray-500';

    public function __construct(
        private readonly FieldNamer $fieldNamer,
        private readonly Escaper $escaper,
        private readonly WysiwygRendererInterface $wysiwygRenderer
    ) {
    }

    /**
     * Render one EAV attribute field. Returns empty string if the field is
     * skipped (removed by override, not applicable to entity type, etc.).
     */
    public function render(EavForm $block, string $attrCode, LayoutInterface $layout): string
    {
        $overrides = $block->getFieldOverrides($attrCode);

        if (!empty($overrides['$remove'])) {
            return '';
        }

        if (!$block->isFieldApplicable($attrCode)) {
            return '';
        }

        if ($block->hasCustomRenderer($attrCode)) {
            return $block->renderCustomField($attrCode);
        }

        $attribute = $block->getAttribute($attrCode);

        if ($attribute === null) {
            return '';
        }

        $fieldType = $block->getFieldType($attribute);

        if ($fieldType === 'skip') {
            return '';
        }

        $fieldPrefix = $block->getDefinition()['settings']['fieldPrefix'] ?? null;
        $fieldName = $this->fieldNamer->name($attrCode, $fieldPrefix);
        $fieldId = $this->fieldNamer->domId($attrCode, 'nebula-eav');
        $fieldValue = $block->getFieldValue($attrCode);

        if (is_array($fieldValue) && $fieldType !== 'multiselect') {
            $fieldValue = implode(',', $fieldValue);
        }

        $label = (string) ($attribute->getStoreLabel() ?: $attribute->getFrontendLabel() ?: $attrCode);
        $isRequired = (bool) $attribute->getIsRequired();
        $note = (string) ($attribute->getNote() ?? '');

        if ($fieldType === 'hidden') {
            // The only "hidden" allowed: it's an explicit opt-in by attribute
            // config — still routed through the model layer via the hidden field.
            return $this->renderHiddenModel($fieldName, (string) ($fieldValue ?? ''));
        }

        if ($fieldType === 'multiselect') {
            return $this->renderMultiselect($block, $layout, $attribute, $attrCode, $fieldName, $fieldValue, $label, $note, $isRequired);
        }

        if ($fieldType === 'image') {
            return $this->renderImage($fieldId, $fieldName, (string) ($fieldValue ?? ''), $label, $isRequired, $note);
        }

        $validationConfig = $this->buildValidationConfig($fieldType, $isRequired, $label);
        $validateAttr = $this->buildValidateAttr($validationConfig);

        $config = [
            'fieldName' => $fieldName,
            'value' => $this->encodeValue($fieldType, $fieldValue),
            'label' => $label,
            'required' => $isRequired,
            'validation' => $validationConfig,
        ];

        if ($fieldType === 'select') {
            $config['options'] = $this->encodeOptions($block->getAttributeOptions($attribute));
        }

        if ($fieldType === 'number') {
            $config['step'] = $attribute->getFrontendInput() === 'price' ? 0.01 : 1;
        }

        return $this->renderFieldShell(
            $fieldType,
            $fieldId,
            $label,
            $isRequired,
            $note,
            $config,
            $validateAttr,
            $block->getAttributeOptions($attribute)
        );
    }

    /**
     * Field shell: label + input wrapper + note. Routes through Alpine
     * `nebulaField_<type>` components that auto-register with nebulaModels.
     */
    private function renderFieldShell(
        string $fieldType,
        string $fieldId,
        string $label,
        bool $isRequired,
        string $note,
        array $config,
        string $validateAttr,
        array $rawOptions
    ): string {
        $required = $isRequired ? '<span class="text-red-500">*</span>' : '';
        $json = $this->jsonConfig($config);

        $input = match ($fieldType) {
            'text', 'password', 'email' => $this->renderTextInput($fieldId, $fieldType, $json, $validateAttr),
            'textarea' => $this->renderTextareaInput($fieldId, $json, $validateAttr),
            'wysiwyg' => $this->wysiwygRenderer->render(
                $fieldId,
                (string) $config['fieldName'],
                (string) $config['value'],
                $json,
                $validateAttr,
                self::INPUT_CLASS
            ),
            'select' => $this->renderSelectInput($fieldId, $json, $validateAttr, $rawOptions),
            'toggle' => $this->renderToggleInput($fieldId, $json, $label),
            'checkbox' => $this->renderCheckboxInput($fieldId, $json, $label),
            'date' => $this->renderDateInput($fieldId, $json, $validateAttr),
            'number' => $this->renderNumberInput($fieldId, $json, $validateAttr),
            'color' => $this->renderColorInput($fieldId, $json),
            default => $this->renderTextInput($fieldId, 'text', $json, $validateAttr),
        };

        $noteHtml = $note !== ''
            ? '<p class="mt-1 text-xs text-gray-500">' . $this->escaper->escapeHtml($note) . '</p>'
            : '';

        return '<div class="grid grid-cols-3 gap-4 py-4">'
            . '<label for="' . $this->escaper->escapeHtmlAttr($fieldId) . '" class="pt-2 text-sm font-medium text-gray-700">'
            . $this->escaper->escapeHtml($label) . ' ' . $required
            . '</label>'
            . '<div class="col-span-2">' . $input . $noteHtml . '</div>'
            . '</div>';
    }

    private function renderTextInput(string $fieldId, string $type, string $json, string $validateAttr): string
    {
        return '<div x-data="nebulaField_text(' . $json . ')">'
            . '<input type="' . $this->escaper->escapeHtmlAttr($type) . '"'
            . ' id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' x-model="value"'
            . $validateAttr
            . ' class="' . self::INPUT_CLASS . '">'
            . '</div>';
    }

    private function renderTextareaInput(string $fieldId, string $json, string $validateAttr): string
    {
        return '<div x-data="nebulaField_textarea(' . $json . ')">'
            . '<textarea id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' x-model="value" rows="5"'
            . $validateAttr
            . ' class="' . self::INPUT_CLASS . '"></textarea>'
            . '</div>';
    }

    private function renderSelectInput(string $fieldId, string $json, string $validateAttr, array $rawOptions): string
    {
        $options = '<option value="">' . $this->escaper->escapeHtml((string) __('-- Please Select --')) . '</option>';

        foreach ($rawOptions as $option) {
            $optVal = is_array($option) ? ($option['value'] ?? '') : $option;
            $optLabel = is_array($option) ? ($option['label'] ?? '') : $option;

            if (is_array($optVal)) {
                continue;
            }

            $options .= '<option value="' . $this->escaper->escapeHtmlAttr((string) $optVal) . '">'
                . $this->escaper->escapeHtml((string) $optLabel)
                . '</option>';
        }

        return '<div x-data="nebulaField_select(' . $json . ')">'
            . '<select id="' . $this->escaper->escapeHtmlAttr($fieldId) . '" x-model="value"'
            . $validateAttr
            . ' class="' . self::INPUT_CLASS . '">' . $options . '</select>'
            . '</div>';
    }

    private function renderToggleInput(string $fieldId, string $json, string $label): string
    {
        $ariaLabel = $this->escaper->escapeHtmlAttr((string) __('Toggle %1', $label));

        return '<div class="flex items-center gap-3 pt-2" x-data="nebulaField_toggle(' . $json . ')">'
            . '<button type="button" @click="value = !value"'
            . ' :class="value ? \'bg-indigo-600\' : \'bg-gray-300 border border-gray-300\'"'
            . ' class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent shadow-sm transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"'
            . ' aria-label="' . $ariaLabel . '" title="' . $ariaLabel . '"'
            . ' role="switch" :aria-checked="value">'
            . '<span :class="value ? \'translate-x-5\' : \'translate-x-0\'"'
            . ' class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-1 ring-gray-200 transition duration-200 ease-in-out"></span>'
            . '</button>'
            . '<span class="text-sm font-semibold" :class="value ? \'text-indigo-600\' : \'text-gray-500\'"'
            . ' x-text="value ? \'' . $this->escaper->escapeJs((string) __('Yes'))
            . '\' : \'' . $this->escaper->escapeJs((string) __('No')) . '\'"></span>'
            . '</div>';
    }

    private function renderCheckboxInput(string $fieldId, string $json, string $label): string
    {
        return '<div class="flex items-center gap-3 pt-2" x-data="nebulaField_checkbox(' . $json . ')">'
            . '<input type="checkbox" id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' x-model="value" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">'
            . '</div>';
    }

    private function renderDateInput(string $fieldId, string $json, string $validateAttr): string
    {
        return '<div x-data="nebulaField_date(' . $json . ')">'
            . '<input type="date" id="' . $this->escaper->escapeHtmlAttr($fieldId) . '" x-model="value"'
            . $validateAttr
            . ' class="' . self::INPUT_CLASS . '">'
            . '</div>';
    }

    private function renderNumberInput(string $fieldId, string $json, string $validateAttr): string
    {
        return '<div x-data="nebulaField_number(' . $json . ')">'
            . '<input type="number" id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' x-model="value" :step="step"'
            . $validateAttr
            . ' class="' . self::INPUT_CLASS . '">'
            . '</div>';
    }

    private function renderColorInput(string $fieldId, string $json): string
    {
        return '<div x-data="nebulaField_color(' . $json . ')">'
            . '<input type="color" id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' x-model="value" class="h-10 w-16 cursor-pointer rounded-lg border-0 p-1 shadow-sm">'
            . '</div>';
    }

    private function renderHiddenModel(string $fieldName, string $value): string
    {
        $json = $this->jsonConfig(['fieldName' => $fieldName, 'value' => $value]);

        return '<div x-data="nebulaField_hidden(' . $json . ')" style="display:none"></div>';
    }

    private function renderImage(string $fieldId, string $fieldName, string $value, string $label, bool $isRequired, string $note): string
    {
        $required = $isRequired ? '<span class="text-red-500">*</span>' : '';
        $preview = $value !== ''
            ? '<div class="mb-2"><img src="' . $this->escaper->escapeUrl($value) . '" class="h-20 rounded" alt=""></div>'
            : '';
        $noteHtml = $note !== ''
            ? '<p class="mt-1 text-xs text-gray-500">' . $this->escaper->escapeHtml($note) . '</p>'
            : '';

        return '<div class="grid grid-cols-3 gap-4 py-4">'
            . '<label for="' . $this->escaper->escapeHtmlAttr($fieldId) . '" class="pt-2 text-sm font-medium text-gray-700">'
            . $this->escaper->escapeHtml($label) . ' ' . $required
            . '</label>'
            . '<div class="col-span-2">' . $preview
            . '<input type="file" id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' name="' . $this->escaper->escapeHtmlAttr($fieldName) . '" accept="image/*"'
            . ' class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">'
            . $noteHtml . '</div></div>';
    }

    private function renderMultiselect(
        EavForm $block,
        LayoutInterface $layout,
        \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute,
        string $attrCode,
        string $fieldName,
        mixed $fieldValue,
        string $label,
        string $note,
        bool $isRequired
    ): string {
        $selectedValues = is_array($fieldValue)
            ? $fieldValue
            : array_filter(explode(',', (string) ($fieldValue ?? '')));
        $selectedValues = array_map('strval', $selectedValues);
        $options = [];

        foreach ($block->getAttributeOptions($attribute) as $option) {
            $optVal = $option['value'];
            if ($optVal === '') {
                continue;
            }
            $options[] = ['value' => $optVal, 'label' => $option['label']];
        }

        /** @var Template $renderer */
        $renderer = $layout->createBlock(Template::class);
        $renderer->setData('module_name', 'Qoliber_NebulaComponent');
        $renderer->setTemplate('Qoliber_NebulaComponent::snippet/searchable_multiselect.phtml');
        // Note: searchable_multiselect uses the field name + "[]" convention internally.
        // We route through the snippet but still rely on the model layer's
        // multiselect serialization when the snippet is wrapped by Alpine.
        $renderer->setData('field_name', $fieldName . '[]');
        $renderer->setData('options', $options);
        $renderer->setData('selected', $selectedValues);
        $renderer->setData('placeholder', (string) __('Search %1...', $label));
        $renderer->setData('label', $label);

        $required = $isRequired ? '<span class="text-red-500">*</span>' : '';
        $noteHtml = $note !== ''
            ? '<p class="mt-1 text-xs text-gray-500">' . $this->escaper->escapeHtml($note) . '</p>'
            : '';

        return '<div class="grid grid-cols-3 gap-4 py-4">'
            . '<label class="pt-2 text-sm font-medium text-gray-700">'
            . $this->escaper->escapeHtml($label) . ' ' . $required
            . '</label>'
            . '<div class="col-span-2">' . $renderer->toHtml() . $noteHtml . '</div></div>';
    }

    private function buildValidationConfig(string $fieldType, bool $isRequired, string $label): array
    {
        $rules = ['label' => $label];

        if ($isRequired) {
            $rules['required'] = true;
        }
        if ($fieldType === 'number') {
            $rules['number'] = true;
        }
        if ($fieldType === 'email') {
            $rules['email'] = true;
        }

        return $rules;
    }

    private function buildValidateAttr(array $validationConfig): string
    {
        $label = (string) ($validationConfig['label'] ?? 'Field');
        unset($validationConfig['label']);

        $rules = array_keys(array_filter(
            $validationConfig,
            static fn (mixed $value): bool => $value === true
        ));

        if ($rules === []) {
            return '';
        }

        return ' data-validate="' . $this->escaper->escapeHtmlAttr(implode('|', $rules)) . '"'
            . ' data-validate-label="' . $this->escaper->escapeHtmlAttr($label) . '"';
    }

    private function encodeValue(string $fieldType, mixed $value): mixed
    {
        if ($fieldType === 'toggle' || $fieldType === 'checkbox') {
            return (bool) $value;
        }

        if ($fieldType === 'date' && $value) {
            try {
                return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
            } catch (\Exception) {
                return '';
            }
        }

        return (string) ($value ?? '');
    }

    private function encodeOptions(array $options): array
    {
        $result = [];

        foreach ($options as $option) {
            $optVal = is_array($option) ? ($option['value'] ?? '') : $option;
            $optLabel = is_array($option) ? ($option['label'] ?? '') : $option;

            if (is_array($optVal)) {
                continue;
            }

            $result[] = ['value' => (string) $optVal, 'label' => (string) $optLabel];
        }

        return $result;
    }

    private function jsonConfig(array $data): string
    {
        // htmlspecialchars so the JSON can live inside x-data="..."
        return htmlspecialchars(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
