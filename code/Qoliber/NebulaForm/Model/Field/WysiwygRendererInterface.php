<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Field;

interface WysiwygRendererInterface
{
    public function render(
        string $fieldId,
        string $fieldName,
        string $value,
        string $json,
        string $validateAttr,
        string $inputClass
    ): string;
}
