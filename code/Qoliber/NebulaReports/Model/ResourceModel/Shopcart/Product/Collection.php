<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Shopcart\Product;

/**
 * Nebula wrapper around the stock Products-in-Carts collection.
 *
 * The stock Grid block calls $collection->prepareActiveCartItems() in
 * _prepareCollection(). Since Nebula's generic CollectionProvider does
 * not know about that setup, we run it idempotently in _beforeLoad()
 * so the same query shape is emitted whether the grid is rendered via
 * the stock block, an export, or Nebula.
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Quote\Item\Collection
{
    private bool $preparedActiveCartItems = false;

    protected function _beforeLoad(): self
    {
        if (!$this->preparedActiveCartItems) {
            $this->prepareActiveCartItems();
            $this->preparedActiveCartItems = true;
        }
        return parent::_beforeLoad();
    }
}
