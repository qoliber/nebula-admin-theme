<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\CheckoutAgreements\Model\ResourceModel\Agreement\Grid\CollectionFactory;
use Qoliber\NebulaComponent\Api\GridDataProviderInterface;
use Qoliber\NebulaTheme\Model\Source\StoreView;

/**
 * Grid data provider for the checkout-agreements listing.
 */
class AgreementGridProvider implements GridDataProviderInterface
{
    /** @var int Upper bound for request-controlled page size (memory-exhaustion guard). */
    private const MAX_PAGE_SIZE = 200;

    /**
     * @param \Magento\CheckoutAgreements\Model\ResourceModel\Agreement\Grid\CollectionFactory $collectionFactory
     * @param \Qoliber\NebulaTheme\Model\Source\StoreView $storeViewSource
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreView $storeViewSource
    ) {
    }

    /**
     * Fetch checkout-agreement rows and total count for the given grid definition/request.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array{items: array<int, array<string, mixed>>, totalCount: int}
     */
    public function getData(array $config, array $params = []): array
    {
        $collection = $this->collectionFactory->create();
        $columns = $config['columns'] ?? [];
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

                if ($condition !== []) {
                    $collection->addFieldToFilter($baseField, $condition);
                }

                continue;
            }

            if ($field === 'stores') {
                $collection->addStoreFilter((int) $value);
                continue;
            }

            $filterType = $columns[$field]['filter'] ?? 'text';
            if ($filterType === 'select') {
                $collection->addFieldToFilter($field, $value);
                continue;
            }

            $collection->addFieldToFilter($field, ['like' => '%' . $value . '%']);
        }

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $collection->addFieldToFilter('name', ['like' => '%' . $search . '%']);
        }

        $sort = (string) ($params['sort'] ?? '');
        $sortDir = strtoupper((string) ($params['sortDir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        if (in_array($sort, ['agreement_id', 'name', 'is_active'], true)) {
            $collection->setOrder($sort, $sortDir);
        }

        $totalCount = (int) $collection->getSize();

        if (array_key_exists('pageSize', $params)) {
            $pageSize = (int) ($params['pageSize'] ?? 0);
            if ($pageSize > 0) {
                $collection->setPageSize(min($pageSize, self::MAX_PAGE_SIZE));
                $collection->setCurPage(max(1, (int) ($params['page'] ?? 1)));
            }
        }

        $storeLabels = $this->getStoreLabels();
        $items = [];

        foreach ($collection as $agreement) {
            $data = $agreement->getData();
            $data['stores'] = $this->formatStoreLabels($agreement->getData('stores'), $storeLabels);
            $items[] = $data;
        }

        return [
            'items' => $items,
            'totalCount' => $totalCount,
        ];
    }

    /**
     * Build a map of store-view id => label for rendering the stores column.
     *
     * @return array<string, string>
     */
    private function getStoreLabels(): array
    {
        $labels = [];

        foreach ($this->storeViewSource->toOptionArray() as $option) {
            $value = $option['value'] ?? null;
            $label = trim((string) ($option['label'] ?? ''));

            if (($option['group'] ?? false) || $value === null || $label === '') {
                continue;
            }

            $labels[(string) $value] = $label;
        }

        return $labels;
    }

    /**
     * Render a comma-separated store-view label string for a stores value.
     *
     * @param mixed $storeIds
     * @param array<string, string> $labels
     * @return string
     */
    private function formatStoreLabels(mixed $storeIds, array $labels): string
    {
        if (!is_array($storeIds)) {
            $storeIds = $storeIds === null || $storeIds === '' ? [] : [$storeIds];
        }

        $normalized = [];
        foreach ($storeIds as $storeId) {
            if ($storeId === null || $storeId === '') {
                continue;
            }

            $normalized[] = (string) $storeId;
        }

        if ($normalized === []) {
            return '';
        }

        if (in_array('0', $normalized, true)) {
            return $labels['0'] ?? (string) __('All Store Views');
        }

        $resolved = [];
        foreach ($normalized as $storeId) {
            $resolved[] = $labels[$storeId] ?? $storeId;
        }

        return implode(', ', array_values(array_unique($resolved)));
    }
}
