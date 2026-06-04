<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Report\Product\Viewed;

/**
 * Pre-configured "Most Viewed Products" report collection for Nebula grids.
 *
 * The stock report relies on the legacy filter form to feed it period + date range.
 * The Nebula port defaults to a daily breakdown over the last 30 days; the user
 * can later wire up grid filters that mutate these via an event observer.
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Report\Product\Viewed\Collection
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

        $to = (new \DateTimeImmutable())->format('Y-m-d');
        $from = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');

        $this->setPeriod('day')
            ->setDateRange($from, $to);
    }
}
