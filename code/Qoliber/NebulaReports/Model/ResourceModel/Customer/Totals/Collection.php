<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Customer\Totals;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;

/**
 * Nebula wrapper around the stock Customers-by-Orders-Total report
 * collection. Same shape as the Orders wrapper — inherits the 13-arg
 * parent constructor untouched.
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Customer\Totals\Collection
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
