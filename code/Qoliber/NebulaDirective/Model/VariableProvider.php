<?php

declare(strict_types=1);

namespace Qoliber\NebulaDirective\Model;

use Magento\Variable\Model\Source\Variables;
use Magento\Variable\Model\Variable;

class VariableProvider
{
    public function __construct(
        private readonly Variable $variableModel,
        private readonly Variables $defaultVariables,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $variables = [];

        foreach ($this->defaultVariables->getData() as $item) {
            $variables[] = [
                'label'     => (string) $item['label'],
                'group'     => (string) ($item['group_label'] ?? ''),
                'type'      => 'default',
                'code'      => (string) $item['value'],
                'directive' => '{{config path="' . $item['value'] . '"}}',
            ];
        }

        foreach ($this->variableModel->getCollection()->toOptionArray() as $item) {
            $variables[] = [
                'label'     => (string) $item['label'],
                'group'     => 'Custom Variables',
                'type'      => 'custom',
                'code'      => (string) $item['value'],
                'directive' => '{{customVar code=' . $item['value'] . '}}',
            ];
        }

        return ['variables' => $variables];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getLookup(): array
    {
        $lookup = [];

        foreach ($this->getAll()['variables'] as $variable) {
            $key = $this->getLookupKey((string) $variable['type'], (string) $variable['code']);
            $lookup[$key] = [
                'label' => (string) $variable['label'],
                'group' => (string) $variable['group'],
                'directive' => (string) $variable['directive'],
            ];
        }

        return $lookup;
    }

    public function getLookupKey(string $type, string $code): string
    {
        return $type . ':' . $code;
    }
}
