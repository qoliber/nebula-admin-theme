<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Api;

/** Data provider for grid listings. getData() must return {items: array[], totalCount: int}. */
interface GridDataProviderInterface extends DataProviderInterface
{
}
