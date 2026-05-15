<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Definition;

class FormDefinitionNormalizer
{
    public function normalize(array $definition): array
    {
        foreach (['_warning', '_generated', '_meta'] as $key) {
            unset($definition[$key]);
        }

        return $definition;
    }
}
