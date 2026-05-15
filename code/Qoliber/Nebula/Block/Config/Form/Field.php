<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Block\Config\Form;

use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Nebula config field renderer.
 * Replaces <table>/<tr>/<td> with <div> grid + Alpine.js for inherit toggle.
 */
class Field extends \Magento\Config\Block\System\Config\Form\Field
{
    private ?string $elementHtmlOverride = null;

    public function setElementHtml(string $html): self
    {
        $this->elementHtmlOverride = $html;

        return $this;
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->elementHtmlOverride ?? parent::_getElementHtml($element);
    }

    public function render(AbstractElement $element): string
    {
        $isCheckboxRequired = $this->_isInheritCheckboxRequired($element);
        $isInherited = (bool) $element->getInherit();

        if ($isInherited && $isCheckboxRequired) {
            $element->setDisabled(true);
        }

        if ($element->getIsDisableInheritance()) {
            $element->setReadonly(true);
        }

        $htmlId = $element->getHtmlId();
        $inheritId = $htmlId . '_inherit';
        $alpineId = 'nebula_' . preg_replace('/[^a-zA-Z0-9]/', '_', $htmlId);

        $html = '<div id="row_' . $htmlId . '" '
            . 'class="nebula-config-field" '
            . ($isCheckboxRequired ? 'x-data="' . $alpineId . '()"' : '')
            . '>';

        // Label
        $html .= '<div class="nebula-config-label">';
        $html .= '<label for="' . $htmlId . '">';
        $html .= '<span' . $this->_renderScopeLabel($element) . '>' . $element->getLabel() . '</span>';
        $html .= '</label>';
        $html .= '</div>';

        // Value
        $html .= '<div class="nebula-config-value">';
        if ($isCheckboxRequired) {
            $html .= '<div :class="inherited ? \'nebula-config-inherited pointer-events-none\' : \'\'">';
        }
        $html .= $this->_getElementHtml($element);
        if ($element->getComment()) {
            $html .= '<p class="nebula-config-note">' . $element->getComment() . '</p>';
        }
        if ($isCheckboxRequired) {
            $html .= '</div>';
        }
        $html .= '</div>';

        // Inherit checkbox
        if ($isCheckboxRequired) {
            $namePrefix = preg_replace('#\[value\](\[\])?$#', '', $element->getName());
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
            $html .= '<span class="nebula-inherit-label">' . $this->_getInheritCheckboxLabel($element) . '</span>';
            $html .= '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // Alpine component for this field
        if ($isCheckboxRequired) {
            $html .= '<script>'
                . 'function ' . $alpineId . '() {'
                . '  return {'
                . '    inherited: ' . ($isInherited ? 'true' : 'false') . ','
                . '    onToggle(el) {'
                . '      this.inherited = el.checked;'
                . '      const row = el.closest(".nebula-config-field");'
                . '      row.querySelectorAll("select, input:not(.nebula-inherit-checkbox), textarea").forEach(function(inp) {'
                . '        inp.disabled = el.checked;'
                . '      });'
                . '    }'
                . '  }'
                . '}'
                . '</script>';
        }

        return $html;
    }
}
