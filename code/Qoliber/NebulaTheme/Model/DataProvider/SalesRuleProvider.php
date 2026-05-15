<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\SalesRule\Model\ResourceModel\Rule as RuleResource;
use Magento\SalesRule\Model\RuleFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class SalesRuleProvider implements DataProviderInterface
{
    public function __construct(
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        // The ActiveRecord model (not the API DTO from RuleRepositoryInterface):
        // its resource _afterLoad hydrates website_ids / customer_group_ids and
        // the primary coupon_code, none of which exist on Magento\SalesRule\Model\Data\Rule.
        $rule = $this->ruleFactory->create()->load((int) $entityId);

        if (!$rule->getId()) {
            return [];
        }

        $connection = $this->ruleResource->getConnection();

        $rawSelect = $connection->select()
            ->from($this->ruleResource->getMainTable(), ['conditions_serialized', 'actions_serialized'])
            ->where('rule_id = ?', (int) $entityId);
        $rawRow = $connection->fetchRow($rawSelect);

        $conditionsSerialized = (is_array($rawRow) && is_string($rawRow['conditions_serialized']))
            ? $rawRow['conditions_serialized']
            : '';
        $actionsSerialized = (is_array($rawRow) && is_string($rawRow['actions_serialized']))
            ? $rawRow['actions_serialized']
            : '';

        $labelSelect = $connection->select()
            ->from($this->ruleResource->getTable('salesrule_label'), ['store_id', 'label'])
            ->where('rule_id = ?', (int) $entityId);
        $labelRows = $connection->fetchAll($labelSelect);

        $storeLabels = [];
        foreach ($labelRows as $labelRow) {
            $storeLabels[(int) $labelRow['store_id']] = (string) $labelRow['label'];
        }

        return [
            'rule_id' => $rule->getRuleId(),
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'is_active' => $rule->getIsActive() ? '1' : '0',
            'website_ids' => $rule->getWebsiteIds(),
            'customer_group_ids' => $rule->getCustomerGroupIds(),
            'coupon_type' => (string) $rule->getCouponType(),
            'coupon_code' => $rule->getCouponCode(),
            'uses_per_coupon' => $rule->getUsesPerCoupon(),
            'uses_per_customer' => $rule->getUsesPerCustomer(),
            'from_date' => $rule->getFromDate(),
            'to_date' => $rule->getToDate(),
            'simple_action' => $rule->getSimpleAction(),
            'discount_amount' => $rule->getDiscountAmount(),
            'discount_qty' => $rule->getDiscountQty(),
            'discount_step' => $rule->getDiscountStep(),
            'stop_rules_processing' => $rule->getStopRulesProcessing() ? '1' : '0',
            'sort_order' => $rule->getSortOrder(),
            'conditions_serialized' => $conditionsSerialized,
            'actions_serialized' => $actionsSerialized,
            'use_auto_generation' => $rule->getUseAutoGeneration() ? '1' : '0',
            'is_rss' => $rule->getIsRss() ? '1' : '0',
            'apply_to_shipping' => $rule->getApplyToShipping() ? '1' : '0',
            'store_labels' => $storeLabels,
        ];
    }
}
