<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Block;

use Magento\Framework\View\Element\Template;

class Field extends Template
{
    /**
     * @return string
     */
    public function getFieldId(): string
    {
        return (string) $this->getData('field_id');
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return (string) $this->getData('field_name');
    }

    /**
     * @return mixed
     */
    public function getFieldValue(): mixed
    {
        return $this->getData('value');
    }

    /**
     * @return array
     */
    public function getFieldConfig(): array
    {
        return (array) $this->getData('config');
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return (bool) $this->getData('required');
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return (bool) $this->getData('disabled');
    }

    /**
     * @return bool
     */
    public function isReadonly(): bool
    {
        return (bool) $this->getData('readonly');
    }

    /**
     * @return string
     */
    public function getPlaceholder(): string
    {
        return (string) $this->getData('placeholder');
    }

    /**
     * @return array
     */
    public function getValidationRules(): array
    {
        return (array) ($this->getFieldConfig()['validation'] ?? []);
    }

    /**
     * Build data-validate attribute.
     *
     * @return string
     */
    public function getValidateAttribute(): string
    {
        $rules = [];
        if ($this->isRequired()) {
            $rules[] = 'required';
        }

        $configRules = $this->getValidationRules();
        foreach ($configRules as $rule => $value) {
            if ($value === true) {
                $rules[] = $rule;
            } elseif ($value !== false) {
                $rules[] = $rule . ':' . $value;
            }
        }

        if (empty($rules)) {
            return '';
        }

        return ' data-validate="' . $this->escapeHtmlAttr(implode('|', $rules)) . '"'
            . ' data-validate-label="' . $this->escapeHtmlAttr($this->translate($this->getData('label') ?: '')) . '"';
    }

    /**
     * @param string $text
     * @return string
     */
    public function translate(string $text): string
    {
        return (string) __($text);
    }
}
