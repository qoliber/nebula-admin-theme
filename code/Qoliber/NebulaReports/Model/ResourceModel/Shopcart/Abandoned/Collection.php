<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Shopcart\Abandoned;

/**
 * Nebula wrapper around the stock Abandoned-Carts collection.
 *
 * Stock Grid block calls prepareForAbandonedReport($storeIds [, $filterData])
 * in _prepareCollection() and then resolveCustomerNames() after parent load.
 * We mirror that setup here so the grid renders correctly under Nebula.
 *
 * Store-scope filtering (from the website/group/store query param) is left
 * to the user's later daterange-filter pass — until then the report runs
 * across all stores.
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Quote\Collection
{
    private bool $preparedForAbandoned = false;

    protected function _beforeLoad(): self
    {
        if (!$this->preparedForAbandoned) {
            $this->prepareForAbandonedReport([]);
            $this->preparedForAbandoned = true;
        }
        return parent::_beforeLoad();
    }

    protected function _afterLoad(): self
    {
        parent::_afterLoad();
        $this->resolveCustomerNames();
        return $this;
    }
}
