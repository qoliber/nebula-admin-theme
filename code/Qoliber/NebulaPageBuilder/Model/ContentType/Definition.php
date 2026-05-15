<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\ContentType;

class Definition
{
    public function __construct(
        private readonly array $data
    ) {
    }

    public function getName(): string
    {
        return $this->data['name'] ?? '';
    }

    public function getLabel(): string
    {
        return $this->data['label'] ?? '';
    }

    public function getIcon(): string
    {
        return $this->data['icon'] ?? '';
    }

    public function getMenuSection(): string
    {
        return $this->data['menuSection'] ?? 'general';
    }

    public function isContainer(): bool
    {
        return !empty($this->data['isContainer']);
    }

    public function getParentRules(): array
    {
        return $this->data['parents'] ?? [];
    }

    public function getChildRules(): array
    {
        return $this->data['children'] ?? [];
    }

    public function getAppearances(): array
    {
        return $this->data['appearances'] ?? [];
    }

    public function getDefaultAppearance(): string
    {
        foreach ($this->getAppearances() as $name => $appearance) {
            if (!empty($appearance['default'])) {
                return $name;
            }
        }

        $keys = array_keys($this->getAppearances());
        return $keys[0] ?? 'default';
    }

    public function getDefaults(): array
    {
        return $this->data['defaults'] ?? [];
    }

    public function getForm(): string
    {
        return $this->data['form'] ?? '';
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
