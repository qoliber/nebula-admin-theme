<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Api;

interface FieldValidatorInterface
{
    public function validate(string $field, mixed $value, array $rules): ?string;
}
