<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Product\Sold;

/**
 * Pre-configured "Products Ordered" report collection for Nebula grids.
 *
 * Stock {@see \Magento\Reports\Block\Adminhtml\Sales\Sold\Grid} pulls date range
 * from the filter form before calling {@see setDateRange()}. The Nebula port
 * currently shows the lifetime view; date filtering can be wired later from the
 * grid's range filters by adding an event observer on `nebula_grid_data_fetched_after`
 * or extending the data provider.
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Product\Sold\Collection
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

        // setDateRange('', '') means lifetime; the parent SQL builder omits the
        // date BETWEEN clause when both bounds are empty strings.
        $this->setDateRange('', '');
    }
}
