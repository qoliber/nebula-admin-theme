<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;
use Qoliber\NebulaComponent\Api\GridDataProviderInterface;

/**
 * Grid data provider for the product attribute-set listing.
 */
class AttributeSetProvider implements GridDataProviderInterface
{
    /** @var int Upper bound for request-controlled page size (memory-exhaustion guard). */
    private const MAX_PAGE_SIZE = 200;

    /**
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $collectionFactory
     * @param \Magento\Catalog\Model\Product $product
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Product $product
    ) {
    }

    /**
     * Fetch attribute-set rows and total count for the given grid definition/request.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array{items: array<int, array<string, mixed>>, totalCount: int}
     */
    public function getData(array $config, array $params = []): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setEntityTypeFilter($this->product->getResource()->getTypeId());

        $columns = $config['columns'] ?? [];
        $filters = $params['filters'] ?? [];
        $processedRanges = [];

        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $baseField = preg_replace('/_(from|to)$/', '', $field);
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
                    $collection->addFieldToFilter($baseField, $condition);
                }
                continue;
            }

            $filterType = $columns[$field]['filter'] ?? 'text';
            if ($filterType === 'select') {
                $collection->addFieldToFilter($field, $value);
            } else {
                $collection->addFieldToFilter($field, ['like' => '%' . $value . '%']);
            }
        }

        $search = $params['search'] ?? '';
        if (!empty($search)) {
            $searchFields = [];
            foreach ($columns as $key => $col) {
                if (!empty($col['searchable'])) {
                    $searchFields[] = $key;
                }
            }
            if (!empty($searchFields)) {
                $conditions = array_fill(0, count($searchFields), ['like' => '%' . $search . '%']);
                $collection->addFieldToFilter($searchFields, $conditions);
            }
        }

        // Sort — only by a declared column, never an arbitrary request field.
        $sort = $params['sort'] ?? '';
        $sortDir = $params['sortDir'] ?? 'asc';
        if ($sort !== '' && isset($columns[$sort])) {
            $collection->setOrder($sort, strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC');
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $pageSize = (int) ($params['pageSize'] ?? 20);
        if ($pageSize > 0) {
            $pageSize = min($pageSize, self::MAX_PAGE_SIZE);
            $collection->setPageSize($pageSize);
            $collection->setCurPage($page);
        }

        return [
            'items' => $collection->toArray()['items'] ?? [],
            'totalCount' => $collection->getSize(),
        ];
    }
}
