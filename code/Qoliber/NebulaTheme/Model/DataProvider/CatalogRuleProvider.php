<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\CatalogRule\Api\CatalogRuleRepositoryInterface;
use Magento\CatalogRule\Model\ResourceModel\Rule as RuleResource;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class CatalogRuleProvider implements DataProviderInterface
{
    public function __construct(
        private readonly CatalogRuleRepositoryInterface $ruleRepository,
        private readonly RuleResource $ruleResource
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $rule = $this->ruleRepository->get((int) $entityId);

        $conditionsSerialized = '';
        $connection = $this->ruleResource->getConnection();
        $select = $connection->select()
            ->from($this->ruleResource->getMainTable(), ['conditions_serialized'])
            ->where('rule_id = ?', (int) $entityId);
        $raw = $connection->fetchOne($select);

        if (is_string($raw) && $raw !== '') {
            $conditionsSerialized = $raw;
        }

        return [
            'rule_id' => $rule->getRuleId(),
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'is_active' => $rule->getIsActive() ? '1' : '0',
            'website_ids' => $rule->getWebsiteIds(),
            'customer_group_ids' => $rule->getCustomerGroupIds(),
            'from_date' => $rule->getFromDate(),
            'to_date' => $rule->getToDate(),
            'simple_action' => $rule->getSimpleAction(),
            'discount_amount' => $rule->getDiscountAmount(),
            'stop_rules_processing' => $rule->getStopRulesProcessing() ? '1' : '0',
            'sort_order' => $rule->getSortOrder(),
            'conditions_serialized' => $conditionsSerialized,
        ];
    }
}
