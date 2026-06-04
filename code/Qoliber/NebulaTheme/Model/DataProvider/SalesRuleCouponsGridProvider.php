<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Framework\App\RequestInterface;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory;
use Qoliber\NebulaComponent\Api\GridDataProviderInterface;

/**
 * Grid data provider for the per-rule coupon-codes table mounted in the
 * sales-rule edit form.
 *
 * Mirrors the rule-bound semantics of
 * \Magento\SalesRule\Block\Adminhtml\Promo\Quote\Edit\Tab\Coupons\Grid: pulls
 * the current rule_id from the request `id` (or `rule_id`) param and applies
 * `addRuleToFilter` + `addGeneratedCouponsFilter` so the grid shows ONLY the
 * non-primary, auto-generated coupons for the rule the user is editing.
 *
 * The generic \Qoliber\NebulaGrid\Model\DataProvider\CollectionProvider has no
 * per-request binding hook, so a focused variant is the least-invasive route
 * to a static rule_id WHERE clause without bolting a new feature onto the
 * generic provider.
 *
 * Items are normalized so the row primary key is exposed under both `id`
 * (consumed by the mass-action checkbox in grid/body.phtml) and `coupon_id`
 * (used by the row-action URL templates and the action column).
 */
class SalesRuleCouponsGridProvider implements GridDataProviderInterface
{
    /** @var int Upper bound for request-controlled page size (memory-exhaustion guard). */
    private const MAX_PAGE_SIZE = 200;

    /**
     * @param \Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory $couponCollectionFactory
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        private readonly CollectionFactory $couponCollectionFactory,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Fetch the rule's generated coupon rows and total count for the request.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array{items: array<int, array<string, mixed>>, totalCount: int}
     */
    public function getData(array $config, array $params = []): array
    {
        $ruleId = (int) ($this->request->getParam('id') ?: $this->request->getParam('rule_id'));

        // No rule context (new-rule screen, AJAX without query string, …):
        // return an empty grid rather than leaking every rule's coupons.
        if ($ruleId <= 0) {
            return ['items' => [], 'totalCount' => 0];
        }

        $collection = $this->couponCollectionFactory->create()
            ->addRuleToFilter($ruleId)
            ->addGeneratedCouponsFilter();

        $columns = $config['columns'] ?? [];

        // ---- filters ----
        $filters = $params['filters'] ?? [];
        $processedRanges = [];
        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $baseField = preg_replace('/_(from|to)$/', '', (string) $field);
            if ($baseField !== $field && isset($columns[$baseField])) {
                if (in_array($baseField, $processedRanges, true)) {
                    continue;
                }
                $processedRanges[] = $baseField;
                $condition = [];
                $from = $filters[$baseField . '_from'] ?? '';
                $to = $filters[$baseField . '_to'] ?? '';
                if ($from !== '' && $from !== null) {
                    $condition['from'] = $from;
                }
                if ($to !== '' && $to !== null) {
                    $condition['to'] = $to;
                }
                if (!empty($condition)) {
                    $filterType = $columns[$baseField]['filter'] ?? 'text';
                    if ($filterType === 'date') {
                        if (isset($condition['from'])) {
                            $condition['from'] .= ' 00:00:00';
                        }
                        if (isset($condition['to'])) {
                            $condition['to'] .= ' 23:59:59';
                        }
                        $condition['date'] = true;
                    }
                    $collection->addFieldToFilter($baseField, $condition);
                }
                continue;
            }

            $filterType = $columns[$field]['filter'] ?? 'text';
            if ($filterType === 'select') {
                $collection->addFieldToFilter((string) $field, $value);
            } else {
                $collection->addFieldToFilter((string) $field, ['like' => '%' . $value . '%']);
            }
        }

        // ---- search ----
        $search = (string) ($params['search'] ?? '');
        if ($search !== '') {
            $searchFields = [];
            foreach ($columns as $key => $col) {
                if (!empty($col['searchable'])) {
                    $searchFields[] = (string) $key;
                }
            }
            if ($searchFields !== []) {
                $conditions = array_fill(0, count($searchFields), ['like' => '%' . $search . '%']);
                $collection->addFieldToFilter($searchFields, $conditions);
            }
        }

        // ---- sort ---- only by a declared column, never an arbitrary field.
        $sort = (string) ($params['sort'] ?? '');
        $sortDir = (string) ($params['sortDir'] ?? 'asc');
        if ($sort !== '' && isset($columns[$sort])) {
            $collection->setOrder($sort, strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC');
        }

        // ---- paging ----
        $page = max(1, (int) ($params['page'] ?? 1));
        $pageSize = (int) ($params['pageSize'] ?? 20);
        if ($pageSize > 0) {
            $pageSize = min($pageSize, self::MAX_PAGE_SIZE);
            $collection->setPageSize($pageSize);
            $collection->setCurPage($page);
        }

        $items = [];
        foreach ($collection as $coupon) {
            $row = $coupon->getData();
            // grid/body.phtml mass-action checkbox reads $item['id']; the
            // salesrule_coupon row only has `coupon_id`, so mirror it across
            // for the checkbox value to be non-empty.
            $row['id'] = (string) ($row['coupon_id'] ?? '');
            $items[] = $row;
        }

        return [
            'items' => $items,
            'totalCount' => $collection->getSize(),
        ];
    }
}
