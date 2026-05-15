<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;
use Qoliber\NebulaComponent\Api\GridDataProviderInterface;

class AttributeSetProvider implements GridDataProviderInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Product $product
    ) {
    }

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

        $sort = $params['sort'] ?? '';
        $sortDir = $params['sortDir'] ?? 'asc';
        if (!empty($sort)) {
            $collection->setOrder($sort, strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC');
        }

        $page = (int) ($params['page'] ?? 1);
        $pageSize = (int) ($params['pageSize'] ?? 20);
        if ($pageSize > 0) {
            $collection->setPageSize($pageSize);
            $collection->setCurPage($page);
        }

        return [
            'items' => $collection->toArray()['items'] ?? [],
            'totalCount' => $collection->getSize(),
        ];
    }
}
