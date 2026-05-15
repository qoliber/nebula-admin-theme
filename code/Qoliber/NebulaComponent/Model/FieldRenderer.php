<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model;

use Qoliber\NebulaComponent\Api\FieldRendererInterface;

class FieldRenderer implements FieldRendererInterface
{
    public function __construct(
        private readonly string $componentName,
        private readonly string $template
    ) {
    }

    public function getComponentName(): string
    {
        return $this->componentName;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }
}
