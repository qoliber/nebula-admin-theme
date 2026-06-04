<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Customer\Orders;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;

/**
 * Nebula wrapper around the stock Customers-by-Number-of-Orders report
 * collection. Parent constructor is the Reports Order Collection's
 * 13-arg signature — we intentionally inherit it unchanged and only
 * augment _beforeLoad() so DI compile stays clean.
 *
 * Reads:
 *   - from: YYYY-MM-DD (default: 30 days ago)
 *   - to:   YYYY-MM-DD (default: today)
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Customer\Orders\Collection
{
    private bool $appliedFilters = false;

    protected function _beforeLoad(): self
    {
        if (!$this->appliedFilters) {
            $this->appliedFilters = true;

            $request = ObjectManager::getInstance()->get(RequestInterface::class);
            $from = (string) $request->getParam('from', date('Y-m-d', strtotime('-30 days')));
            $to   = (string) $request->getParam('to', date('Y-m-d'));

            $this->setDateRange($from . ' 00:00:00', $to . ' 23:59:59');
            $this->setStoreIds([]);
        }
        return parent::_beforeLoad();
    }
}
