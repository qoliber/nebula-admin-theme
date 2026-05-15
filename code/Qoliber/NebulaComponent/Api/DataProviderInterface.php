<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/** @deprecated Use GridDataProviderInterface or FormDataProviderInterface instead. */
interface DataProviderInterface
{
    /**
     * Get data for grid or form rendering.
     *
     * For grids, return: ['items' => array[], 'totalCount' => int]
     * For forms, return: ['field_name' => mixed, ...] with optional '_entity' key for the loaded entity object
     *
     * @param array $config Data source configuration from JSON definition
     * @param array $params Request parameters (page, pageSize, sort, sortDir, filters, search, entityId)
     * @return array
     */
    public function getData(array $config, array $params = []): array;
}
