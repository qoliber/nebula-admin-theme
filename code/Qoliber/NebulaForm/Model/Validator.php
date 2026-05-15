<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model;

use Magento\Framework\ObjectManagerInterface;
use Qoliber\NebulaForm\Api\FieldValidatorInterface;

class Validator
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    public function validate(array $data, array $definition): array
    {
        $errors = [];

        $fieldsets = $definition['fieldsets'] ?? [];

        foreach ($fieldsets as $fieldset) {
            $fields = $fieldset['fields'] ?? [];

            foreach ($fields as $fieldKey => $fieldConfig) {
                $value = $data[$fieldKey] ?? null;
                $rules = $fieldConfig['validation'] ?? [];

                if (isset($rules['required']) && $rules['required']) {
                    if ($value === null || $value === '') {
                        $errors[$fieldKey] = ($fieldConfig['label'] ?? $fieldKey) . ' is required';
                        continue;
                    }
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $error = $this->validateField($fieldKey, $value, $rules, $fieldConfig);

                if ($error !== null) {
                    $errors[$fieldKey] = $error;
                }
            }
        }

        return $errors;
    }

    private function validateField(string $fieldKey, mixed $value, array $rules, array $fieldConfig): ?string
    {
        $label = $fieldConfig['label'] ?? $fieldKey;

        if (isset($rules['minLength']) && is_string($value) && mb_strlen($value) < (int) $rules['minLength']) {
            return sprintf('%s must be at least %d characters', $label, $rules['minLength']);
        }

        if (isset($rules['maxLength']) && is_string($value) && mb_strlen($value) > (int) $rules['maxLength']) {
            return sprintf('%s must not exceed %d characters', $label, $rules['maxLength']);
        }

        if (isset($rules['pattern']) && is_string($value) && !preg_match($rules['pattern'], $value)) {
            return sprintf('%s has an invalid format', $label);
        }

        if (isset($rules['min']) && is_numeric($value) && (float) $value < (float) $rules['min']) {
            return sprintf('%s must be at least %s', $label, $rules['min']);
        }

        if (isset($rules['max']) && is_numeric($value) && (float) $value > (float) $rules['max']) {
            return sprintf('%s must not exceed %s', $label, $rules['max']);
        }

        if (isset($rules['email']) && $rules['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return sprintf('%s must be a valid email address', $label);
        }

        if (isset($rules['custom']) && is_string($rules['custom'])) {
            $customValidator = $this->objectManager->get($rules['custom']);

            if ($customValidator instanceof FieldValidatorInterface) {
                return $customValidator->validate($fieldKey, $value, $rules);
            }
        }

        return null;
    }
}
