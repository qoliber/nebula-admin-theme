<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ArrayFieldRenderPlugin
{
    public function aroundRender(AbstractFieldArray $subject, callable $proceed, AbstractElement $element): string
    {
        $isCheckboxRequired = $this->isInheritCheckboxRequired($element);
        $isInherited = (bool) $element->getInherit();

        if ($isInherited && $isCheckboxRequired) {
            $element->setDisabled(true);
        }

        if ($element->getIsDisableInheritance()) {
            $element->setReadonly(true);
        }

        $htmlId = (string) $element->getHtmlId();
        $inheritId = $htmlId . '_inherit';
        $alpineId = 'nebula_' . preg_replace('/[^a-zA-Z0-9]/', '_', $htmlId);

        $html = '<div id="row_' . $htmlId . '" '
            . 'class="nebula-config-field" '
            . ($isCheckboxRequired ? 'x-data="' . $alpineId . '()"' : '')
            . '>';

        $html .= '<div class="nebula-config-label">';
        $html .= '<label for="' . $htmlId . '">';
        $html .= '<span' . $this->renderScopeLabel($element) . '>' . $element->getLabel() . '</span>';
        $html .= '</label>';
        $html .= '</div>';

        $html .= '<div class="nebula-config-value">';
        if ($isCheckboxRequired) {
            $html .= '<div :class="inherited ? \'nebula-config-inherited pointer-events-none\' : \'\'">';
        }

        $html .= $this->callProtectedGetElementHtml($subject, $element);

        if ($element->getComment()) {
            $html .= '<p class="nebula-config-note">' . $element->getComment() . '</p>';
        }

        if ($isCheckboxRequired) {
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($isCheckboxRequired) {
            $namePrefix = preg_replace('#\\[value\\](\\[\\])?$#', '', (string) $element->getName());
            $checkedAttr = $isInherited ? ' checked="checked"' : '';
            $disabledAttr = $element->getIsDisableInheritance() ? ' disabled="disabled" readonly="1"' : '';

            $html .= '<div class="nebula-config-inherit">';
            $html .= '<label class="nebula-inherit-toggle" for="' . $inheritId . '">';
            $html .= '<input id="' . $inheritId . '" '
                . 'name="' . $namePrefix . '[inherit]" '
                . 'type="checkbox" value="1" '
                . 'class="nebula-inherit-checkbox" '
                . '@change="onToggle($el)" '
                . $checkedAttr . $disabledAttr . '/>';
            $html .= '<span class="nebula-inherit-label">' . $this->getInheritCheckboxLabel($element) . '</span>';
            $html .= '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';

        if ($isCheckboxRequired) {
            $html .= '<script>'
                . 'function ' . $alpineId . '() {'
                . '  return {'
                . '    inherited: ' . ($isInherited ? 'true' : 'false') . ','
                . '    onToggle(el) {'
                . '      this.inherited = el.checked;'
                . '      const row = el.closest(".nebula-config-field");'
                . '      row.querySelectorAll("select, input:not(.nebula-inherit-checkbox), textarea, button").forEach(function(inp) {'
                . '        inp.disabled = el.checked;'
                . '      });'
                . '      const arrayRoot = row.querySelector(".nebula-config-array");'
                . '      if (arrayRoot) {'
                . '        arrayRoot.dispatchEvent(new CustomEvent("nebula-array-disabled-changed", { detail: { disabled: el.checked } }));'
                . '      }'
                . '    }'
                . '  }'
                . '}'
                . '</script>';
        }

        return $html;
    }

    private function callProtectedGetElementHtml(AbstractFieldArray $subject, AbstractElement $element): string
    {
        $method = new \ReflectionMethod(AbstractFieldArray::class, '_getElementHtml');

        /** @var string $html */
        $html = $method->invoke($subject, $element);

        return $html;
    }

    private function isInheritCheckboxRequired(AbstractElement $element): bool
    {
        return (bool) ($element->getCanUseWebsiteValue()
            || $element->getCanUseDefaultValue()
            || $element->getCanRestoreToDefault());
    }

    private function getInheritCheckboxLabel(AbstractElement $element): string
    {
        if ($element->getCanUseDefaultValue()) {
            return (string) __('Use Default');
        }

        if ($element->getCanUseWebsiteValue()) {
            return (string) __('Use Website');
        }

        return (string) __('Use system value');
    }

    private function renderScopeLabel(AbstractElement $element): string
    {
        if (!$element->getScope()) {
            return '';
        }

        return ' data-config-scope="' . $element->getScopeLabel() . '"';
    }
}
