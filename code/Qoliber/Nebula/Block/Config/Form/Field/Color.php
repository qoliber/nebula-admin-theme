<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Block\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Nebula color picker field renderer.
 * Renders an Alpine.js color input alongside the text input.
 */
class Color extends \Qoliber\Nebula\Block\Config\Form\Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $htmlId = $element->getHtmlId();
        $name = $element->getName();
        $value = trim((string) $element->getEscapedValue(), '#');
        $disabled = $element->getDisabled() ? ' disabled="disabled"' : '';
        $alpineId = 'nebulaColor_' . preg_replace('/[^a-zA-Z0-9]/', '_', $htmlId);

        return <<<HTML
<div x-data="{$alpineId}()" class="flex items-center gap-3">
    <input type="color"
           :value="'#' + hex"
           @input="hex = \$el.value.replace('#', '').toUpperCase()"
           class="h-10 w-10 cursor-pointer rounded-lg border border-gray-300 p-0.5 shadow-sm appearance-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded-md [&::-webkit-color-swatch]:border-0 [&::-moz-color-swatch]:rounded-md [&::-moz-color-swatch]:border-0"{$disabled}/>
    <input id="{$htmlId}"
           name="{$name}"
           type="text"
           x-model="hex"
           maxlength="6"
           placeholder="FFFFFF"
           class="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono text-gray-900 bg-white shadow-sm
                  focus:border-nebula-500 focus:ring-2 focus:ring-nebula-500/20 focus:outline-none transition"{$disabled}/>

</div>
<script>
function {$alpineId}() {
    return { hex: '{$value}' }
}
</script>
HTML;
    }
}
