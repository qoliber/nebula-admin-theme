<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Field;

use Magento\Framework\Escaper;

class DefaultWysiwygRenderer implements WysiwygRendererInterface
{
    public function __construct(
        private readonly Escaper $escaper
    ) {
    }

    public function render(
        string $fieldId,
        string $fieldName,
        string $value,
        string $json,
        string $validateAttr,
        string $inputClass
    ): string
    {
        return '<div x-data="nebulaField_textarea(' . $json . ')">'
            . '<textarea id="' . $this->escaper->escapeHtmlAttr($fieldId) . '"'
            . ' name="' . $this->escaper->escapeHtmlAttr($fieldName) . '"'
            . ' x-model="value" rows="5"'
            . $validateAttr
            . ' class="' . $this->escaper->escapeHtmlAttr($inputClass) . '">'
            . $this->escaper->escapeHtml($value)
            . '</textarea>'
            . '</div>';
    }
}
