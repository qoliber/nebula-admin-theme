<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model;

/**
 * Builds HTML form input names from an attribute code + optional prefix.
 *
 * Replaces ad-hoc string concatenation such as `$prefix . '[' . $code . ']'`
 * scattered across form templates.
 */
class FieldNamer
{
    /**
     * Build a field name for an attribute.
     *
     * With a prefix, produces `prefix[attrCode]`; without, returns the raw
     * attribute code.
     */
    public function name(string $attrCode, ?string $prefix = null): string
    {
        if ($prefix === null || $prefix === '') {
            return $attrCode;
        }

        return $prefix . '[' . $attrCode . ']';
    }

    /**
     * Build a bracketed array field name, e.g. `prefix[attrCode][]` or
     * `attrCode[]` when no prefix is given. Useful for multiselect fields.
     */
    public function bracketedName(string $attrCode, ?string $prefix = null): string
    {
        return $this->name($attrCode, $prefix) . '[]';
    }

    /**
     * Convenience: build a DOM id from an attribute code and optional owner.
     */
    public function domId(string $attrCode, string $owner = 'nebula-field'): string
    {
        return $owner . '-' . $attrCode;
    }
}
