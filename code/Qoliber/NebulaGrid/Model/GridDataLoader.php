<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Qoliber\NebulaComponent\Model\DataProviderResolver;
use Qoliber\NebulaComponent\Model\Event\GridDataEvent;

/**
 * Turns a grid definition + request into the `{items, totalCount}` payload.
 *
 * Keeps the request-shape logic (page/sort/filters/search extraction) out
 * of {@see \Qoliber\NebulaGrid\Block\Grid}.
 *
 * After the provider returns data, fires `nebula_grid_data_fetched_after` with
 * a typed {@see GridDataEvent} so observers can mutate items / totalCount
 * before render.
 */
class GridDataLoader
{
    public function __construct(
        private readonly DataProviderResolver $dataProviderResolver,
        private readonly EventManagerInterface $eventManager
    ) {
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{items: array<int, array<string, mixed>>, totalCount: int}
     */
    public function load(array $definition, RequestInterface $request, int $page, int $pageSize, string $sort, string $sortDir): array
    {
        $providerAlias = (string) ($definition['dataSource']['provider'] ?? '');

        if ($providerAlias === '') {
            return ['items' => [], 'totalCount' => 0];
        }

        $config = $definition['dataSource']['config'] ?? [];
        $config['columns'] = $definition['columns'] ?? [];

        $params = [
            'page' => $page,
            'pageSize' => $pageSize,
            'sort' => $sort,
            'sortDir' => $sortDir,
            'filters' => (array) $request->getParam('filters', []),
            'search' => (string) $request->getParam('search', ''),
        ];

        $data = $this->dataProviderResolver->fetch($providerAlias, $config, $params);

        $event = new GridDataEvent(
            (string) ($definition['id'] ?? ''),
            $config,
            $params,
            array_values($data['items'] ?? []),
            (int) ($data['totalCount'] ?? 0)
        );
        $this->eventManager->dispatch('nebula_grid_data_fetched_after', ['event' => $event]);

        return [
            'items' => $event->getItems(),
            'totalCount' => $event->getTotalCount(),
        ];
    }
}
