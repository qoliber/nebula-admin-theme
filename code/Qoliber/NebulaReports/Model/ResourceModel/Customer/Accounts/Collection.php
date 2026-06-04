<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Customer\Accounts;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;

/**
 * Nebula wrapper around the stock New Accounts report collection.
 *
 * The parent class extends the heavyweight Customer EAV collection, whose
 * constructor takes 14 dependencies. Rather than re-declare every one of
 * them in our wrapper (a recipe for DI compile drift), we let the parent
 * keep its signature untouched and just override `_beforeLoad()` to read
 * the Nebula GET params off the request via ObjectManager.
 *
 * Reads:
 *   - from: YYYY-MM-DD (default: 30 days ago)
 *   - to:   YYYY-MM-DD (default: today)
 */
class Collection extends \Magento\Reports\Model\ResourceModel\Accounts\Collection
{
    private bool $appliedFilters = false;

    protected function _beforeLoad(): self
    {
        if (!$this->appliedFilters) {
            $this->appliedFilters = true;

            $request = ObjectManager::getInstance()->get(RequestInterface::class);
            $from = (string) $request->getParam('from', date('Y-m-d', strtotime('-30 days')));
            $to   = (string) $request->getParam('to', date('Y-m-d'));

            // Account-grid dates are inclusive datetime ranges.
            $this->setDateRange($from . ' 00:00:00', $to . ' 23:59:59');
        }
        return parent::_beforeLoad();
    }
}
