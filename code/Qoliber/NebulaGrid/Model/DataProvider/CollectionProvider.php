<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Model\DataProvider;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Qoliber\NebulaComponent\Api\GridDataProviderInterface;
use Qoliber\NebulaComponent\Exception\UnknownAliasException;
use Qoliber\NebulaGrid\Api\CollectionRegistryInterface;

/**
 * Generic grid data provider backed by a Magento resource collection.
 *
 * Resolves a DI-declared collection alias to an FQCN, applies whitelisted
 * filters/sort/pagination from the request, and returns the grid payload.
 */
class CollectionProvider implements GridDataProviderInterface
{
    /**
     * Upper bound for request-controlled page size; prevents memory-exhaustion
     * via an arbitrarily large ?pageSize value.
     *
     * @var int
     */
    private const MAX_PAGE_SIZE = 200;

    /**
     * ObjectManager::create() is used to instantiate per-call collections
     * because collections carry stateful filter/page cursors and must not be
     * shared. The class name is NOT taken from JSON: it comes from
     * $collectionRegistry->resolve($alias), where the alias was declared in DI
     * (etc/di.xml) at compile time.
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Qoliber\NebulaGrid\Api\CollectionRegistryInterface $collectionRegistry
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger,
        private readonly CollectionRegistryInterface $collectionRegistry
    ) {
    }

    /**
     * Fetch the grid rows and total count for the given definition and request.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     * @return array{items: array<int, array<string, mixed>>, totalCount: int}
     */
    public function getData(array $config, array $params = []): array
    {
        $alias = (string) ($config['collection'] ?? '');
        if ($alias === '') {
            return ['items' => [], 'totalCount' => 0];
        }

        try {
            $collectionClass = $this->collectionRegistry->resolve($alias);
        } catch (UnknownAliasException $e) {
            $this->logger->warning(
                'NebulaGrid: unknown collection alias',
                ['alias' => $alias, 'message' => $e->getMessage()]
            );
            return ['items' => [], 'totalCount' => 0];
        }

        $collection = $this->objectManager->create($collectionClass);

        if (!$collection instanceof AbstractCollection) {
            $this->logger->warning(
                'NebulaGrid: collection alias resolves to non-collection class',
                ['alias' => $alias, 'class' => $collectionClass]
            );
            return ['items' => [], 'totalCount' => 0];
        }

        $method = $config['method'] ?? '';
        if (!empty($method) && method_exists($collection, $method)) {
            $collection->$method();
        }

        $columns = $config['columns'] ?? [];
        $filters = $params['filters'] ?? [];
        $processedRanges = [];

        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            // Range filters: field_from / field_to
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
                    $collection->addFieldToFilter($baseField, $condition);
                }
                continue;
            }

            // Ignore any filter on a column not declared in the grid definition:
            // a request must not be able to filter on arbitrary DB columns.
            if (!isset($columns[$field])) {
                continue;
            }

            // Select filter: exact match
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
            foreach ($config['columns'] ?? [] as $key => $col) {
                if (!empty($col['searchable'])) {
                    $searchFields[] = $key;
                }
            }
            if (!empty($searchFields)) {
                $conditions = array_fill(0, count($searchFields), ['like' => '%' . $search . '%']);
                $collection->addFieldToFilter($searchFields, $conditions);
            }
        }

        // Only sort by a column the grid actually declares — never by an
        // arbitrary request-supplied identifier.
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

        // Some real Magento collections add filters in _initSelect() without
        // qualifying with `main_table.`, then later self-join (e.g. theme grid),
        // producing ambiguous-column SQL errors. Best-effort: fall back to an
        // empty grid + logged error instead of breaking the whole page.
        try {
            return [
                'items' => $collection->toArray()['items'] ?? [],
                'totalCount' => $collection->getSize(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Nebula CollectionProvider failed to fetch %s: %s',
                $collectionClass,
                $e->getMessage()
            ));
            return [
                'items' => [],
                'totalCount' => 0,
            ];
        }
    }
}
