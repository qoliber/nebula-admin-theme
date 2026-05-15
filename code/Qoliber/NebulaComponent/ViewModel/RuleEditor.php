<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Combine as BaseCombine;

/**
 * ViewModel for the `snippet/rule_editor.phtml` template.
 *
 * Replaces the template's ObjectManager usages with constructor-injected
 * factories. The phtml reads the JSON condition data + rule type from the
 * renderer's section data, then asks this ViewModel to enumerate the
 * available attributes / condition types.
 *
 * Supported rule types:
 *   - `catalog`        — \Magento\CatalogRule\Model\Rule\Condition\Combine root,
 *                        binds to `conditions_serialized`, POSTs as `rule[conditions][...]`.
 *   - `sales`          — \Magento\SalesRule\Model\Rule\Condition\Combine root,
 *                        binds to `conditions_serialized`, POSTs as `rule[conditions][...]`.
 *   - `sales_actions`  — \Magento\SalesRule\Model\Rule\Condition\Product\Combine root,
 *                        binds to `actions_serialized`, POSTs as `rule[actions][...]`.
 *                        Used for the sales rule "Apply To" tree (a.k.a. Magento's
 *                        `actions_apply_to` container) so two trees can coexist
 *                        on the same form without colliding in the Alpine store
 *                        or the POST payload.
 */
class RuleEditor implements ArgumentInterface
{
    /** @var array<string, string> Magento condition input-type → Nebula input type */
    private const INPUT_TYPE_MAP = [
        'text' => 'string', 'textarea' => 'string', 'date' => 'date', 'datetime' => 'date',
        'boolean' => 'boolean', 'select' => 'select', 'multiselect' => 'multiselect',
        'price' => 'numeric', 'weight' => 'numeric', 'int' => 'numeric',
    ];

    public function __construct(
        private readonly \Magento\CatalogRule\Model\Rule\Condition\CombineFactory $catalogCombineFactory,
        private readonly \Magento\SalesRule\Model\Rule\Condition\CombineFactory $salesCombineFactory,
        private readonly \Magento\SalesRule\Model\Rule\Condition\Product\CombineFactory $salesProductCombineFactory,
        private readonly \Magento\CatalogRule\Model\Rule\Condition\ProductFactory $catalogProductConditionFactory,
        private readonly \Magento\SalesRule\Model\Rule\Condition\ProductFactory $salesProductConditionFactory
    ) {
    }

    /**
     * Build the full config payload consumed by the `nebulaRuleEditor`
     * Alpine component.
     *
     * @param array<string, mixed> $entityData
     * @return array{
     *     conditions: array<string, mixed>,
     *     availableAttributes: array<int, array{value:string, label:string, inputType:string, options:array<int, array{value:string, label:string}>, conditionClass:string}>,
     *     conditionTypes: array<int, array{value:string, label:string}>,
     *     ruleType: string,
     *     fieldPrefix: string,
     *     modelKey: string,
     *     combineClass: string,
     * }
     */
    public function buildConfig(array $entityData, string $ruleType = 'catalog', string $fieldPrefix = 'rule'): array
    {
        $modelKey = $this->modelKey($ruleType);

        return [
            'conditions' => $this->decodeTree($entityData, $ruleType, $modelKey),
            'availableAttributes' => $this->listAvailableAttributes($ruleType),
            'conditionTypes' => $this->listConditionTypes($ruleType),
            'ruleType' => $ruleType,
            'fieldPrefix' => $fieldPrefix,
            'modelKey' => $modelKey,
            'combineClass' => $this->combineClass($ruleType),
        ];
    }

    /**
     * @param array<string, mixed> $entityData
     * @return array<string, mixed>
     */
    private function decodeTree(array $entityData, string $ruleType, string $modelKey): array
    {
        // Sales-actions tree lives in `actions_serialized`; conditions tree in `conditions_serialized`.
        $sourceField = $modelKey === 'actions' ? 'actions_serialized' : 'conditions_serialized';
        $serialized = $entityData[$sourceField] ?? '';

        if (is_string($serialized) && $serialized !== '') {
            try {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded) && $decoded !== []) {
                    return $decoded;
                }
            } catch (\JsonException) {
                // fall through to defaults
            }
        }

        return [
            'type' => $this->combineClass($ruleType),
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [],
        ];
    }

    /**
     * @return array<int, array{value:string, label:string, inputType:string, options:array<int, array{value:string, label:string}>, conditionClass:string}>
     */
    private function listAvailableAttributes(string $ruleType): array
    {
        $combine = $this->createCombine($ruleType);

        try {
            $newChildOptions = $combine->getNewChildSelectOptions();
        } catch (\Throwable) {
            return [];
        }

        $available = [];

        foreach ($newChildOptions as $option) {
            if (empty($option['value']) || !is_array($option['value'])) {
                continue;
            }

            foreach ($option['value'] as $subOption) {
                if (empty($subOption['value']) || !is_string($subOption['value'])) {
                    continue;
                }

                [$condClass, $attrCode] = array_pad(explode('|', $subOption['value'], 2), 2, null);

                if ($attrCode === null) {
                    continue;
                }

                $available[] = $this->describeAttribute(
                    (string) $condClass,
                    (string) $attrCode,
                    (string) ($subOption['label'] ?? $attrCode)
                );
            }
        }

        return $available;
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    private function listConditionTypes(string $ruleType): array
    {
        $combine = $this->createCombine($ruleType);
        $types = [];

        try {
            foreach ($combine->getNewChildSelectOptions() as $option) {
                if (empty($option['value']) || is_array($option['value'])) {
                    continue;
                }

                $types[] = [
                    'value' => (string) $option['value'],
                    'label' => (string) ($option['label'] ?? $option['value']),
                ];
            }
        } catch (\Throwable) {
            // fall through to default
        }

        if ($types === []) {
            $types[] = [
                'value' => $this->defaultLeafClass($ruleType),
                'label' => 'Product Attribute',
            ];
        }

        return $types;
    }

    /**
     * @return array{value:string, label:string, inputType:string, options:array<int, array{value:string, label:string}>, conditionClass:string}
     */
    private function describeAttribute(string $condClass, string $attrCode, string $label): array
    {
        $inputType = 'string';
        $options = [];

        try {
            $condition = $this->createLeafCondition($condClass);
            $condition->setAttribute($attrCode);
            $condition->loadAttributeOptions();
            $condition->loadOperatorOptions();

            $magInputType = (string) $condition->getInputType();
            $inputType = self::INPUT_TYPE_MAP[$magInputType] ?? ($magInputType ?: 'string');

            foreach ((array) $condition->getValueSelectOptions() as $vo) {
                if (is_array($vo) && isset($vo['value']) && !is_array($vo['value']) && $vo['value'] !== '') {
                    $options[] = [
                        'value' => (string) $vo['value'],
                        'label' => (string) ($vo['label'] ?? $vo['value']),
                    ];
                }
            }
        } catch (\Throwable) {
            // keep defaults
        }

        return [
            'value' => $attrCode,
            'label' => $label,
            'inputType' => $inputType,
            'options' => $options,
            'conditionClass' => $condClass,
        ];
    }

    private function createCombine(string $ruleType): BaseCombine
    {
        return match ($ruleType) {
            'sales' => $this->salesCombineFactory->create(),
            'sales_actions' => $this->salesProductCombineFactory->create(),
            default => $this->catalogCombineFactory->create(),
        };
    }

    private function createLeafCondition(string $condClass): AbstractCondition
    {
        // The leaf condition class comes from Magento's own select-options list;
        // both CatalogRule and SalesRule expose typed ProductFactory/AbstractFactory
        // pairs for their respective subclasses. We can't predict what third-party
        // subclasses configure, so we route to the closest matching factory by
        // class-name heuristic.
        if (str_contains($condClass, 'SalesRule')) {
            return $this->salesProductConditionFactory->create();
        }

        return $this->catalogProductConditionFactory->create();
    }

    private function combineClass(string $ruleType): string
    {
        return match ($ruleType) {
            'sales' => \Magento\SalesRule\Model\Rule\Condition\Combine::class,
            'sales_actions' => \Magento\SalesRule\Model\Rule\Condition\Product\Combine::class,
            default => \Magento\CatalogRule\Model\Rule\Condition\Combine::class,
        };
    }

    private function defaultLeafClass(string $ruleType): string
    {
        return match ($ruleType) {
            'sales', 'sales_actions' => \Magento\SalesRule\Model\Rule\Condition\Product::class,
            default => \Magento\CatalogRule\Model\Rule\Condition\Product::class,
        };
    }

    /**
     * Map a rule type to the POST/store key the editor should bind to.
     * Sales-rule actions tree binds to `actions_serialized` and POSTs as
     * `rule[actions][...]`; everything else uses the conditions tree.
     */
    private function modelKey(string $ruleType): string
    {
        return $ruleType === 'sales_actions' ? 'actions' : 'conditions';
    }
}
