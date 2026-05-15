<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Qoliber\NebulaComponent\Api\GridDataProviderInterface;

class CustomerGridProvider implements GridDataProviderInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect([
            'firstname',
            'lastname',
            'email',
            'group_id',
            'created_at',
            'website_id',
            'dob',
            'gender'
        ]);

        $collection->joinAttribute(
            'billing_postcode',
            'customer_address/postcode',
            'default_billing',
            null,
            'left'
        );
        $collection->joinAttribute(
            'billing_city',
            'customer_address/city',
            'default_billing',
            null,
            'left'
        );
        $collection->joinAttribute(
            'billing_telephone',
            'customer_address/telephone',
            'default_billing',
            null,
            'left'
        );
        $collection->joinAttribute(
            'billing_country_id',
            'customer_address/country_id',
            'default_billing',
            null,
            'left'
        );

        $columns = $config['columns'] ?? [];

        $search = $params['search'] ?? '';
        if (!empty($search)) {
            $searchFields = [];
            foreach ($columns as $key => $col) {
                if (!empty($col['searchable'])) {
                    $searchFields[] = $key;
                }
            }
            if (!empty($searchFields)) {
                $conditions = [];
                foreach ($searchFields as $field) {
                    $conditions[] = ['attribute' => $field, 'like' => '%' . $search . '%'];
                }
                $collection->addAttributeToFilter($conditions);
            }
        }

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
                    $collection->addAttributeToFilter($baseField, $condition);
                }
                continue;
            }

            $filterType = $columns[$field]['filter'] ?? 'text';
            if ($filterType === 'select') {
                $collection->addAttributeToFilter($field, $value);
            } else {
                $collection->addAttributeToFilter($field, ['like' => '%' . $value . '%']);
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

        $items = [];
        foreach ($collection as $customer) {
            $data = $customer->getData();
            $data['name'] = $customer->getFirstname() . ' ' . $customer->getLastname();
            $items[] = $data;
        }

        return [
            'items' => $items,
            'totalCount' => $collection->getSize(),
        ];
    }
}
