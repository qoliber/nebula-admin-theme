<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Event;

/**
 * Typed payload for `nebula_grid_data_fetched_after`.
 *
 * Observers may mutate the item list (e.g. enrich rows, mask columns) via
 * {@see self::setItems()}; `totalCount` is informational and authoritative.
 *
 * @api
 */
class GridDataEvent
{
    /**
     * @param string $gridId grid definition id
     * @param array<string, mixed> $config resolved `dataSource.config` payload
     * @param array<string, mixed> $params runtime params (page/pageSize/filters/search/sort/sortDir)
     * @param list<array<string, mixed>> $items
     * @param int $totalCount
     */
    public function __construct(
        private readonly string $gridId,
        private readonly array $config,
        private readonly array $params,
        private array $items,
        private int $totalCount
    ) {
    }

    public function getGridId(): string
    {
        return $this->gridId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public function setItems(array $items): void
    {
        $this->items = array_values($items);
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): void
    {
        $this->totalCount = max(0, $totalCount);
    }
}
