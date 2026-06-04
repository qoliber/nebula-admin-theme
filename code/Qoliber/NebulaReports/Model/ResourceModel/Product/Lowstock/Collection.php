<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Product\Lowstock;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Data\Collection as DataCollection;

/**
 * Pre-configured low-stock collection for the Nebula grid framework.
 *
 * The stock {@see \Magento\Reports\Model\ResourceModel\Product\Lowstock\Collection} requires
 * the controller block to call a chain of join/filter methods before it is usable.
 * Nebula's generic {@see \Qoliber\NebulaGrid\Model\DataProvider\CollectionProvider}
 * resolves a collection by alias and immediately runs filter/sort/page logic on it,
 * so we wrap the stock collection and run that setup chain in {@see _beforeLoad}.
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Product\Lowstock\Collection
{
    private bool $nebulaConfigured = false;

    /**
     * @inheritDoc
     */
    protected function _beforeLoad()
    {
        $this->configureNebula();

        return parent::_beforeLoad();
    }

    /**
     * @inheritDoc
     */
    public function getSelectCountSql()
    {
        $this->configureNebula();

        return parent::getSelectCountSql();
    }

    private function configureNebula(): void
    {
        if ($this->nebulaConfigured) {
            return;
        }

        $this->nebulaConfigured = true;

        $this->addAttributeToSelect('*')
            ->filterByIsQtyProductTypes()
            ->joinInventoryItem('qty')
            ->useManageStockFilter(null)
            ->useNotifyStockQtyFilter(null)
            ->setOrder('qty', DataCollection::SORT_ORDER_ASC)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);
    }
}
