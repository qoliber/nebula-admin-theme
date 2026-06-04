<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Sales\Invoiced;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\ResourceModel\Report as SalesReportResource;

/**
 * Nebula wrapper around the stock Invoiced report collection.
 *
 * Reads simple GET params (`period`, `from`, `to`) in {@see self::_beforeLoad()}
 * and forwards them to the parent's `setPeriod()/setDateRange()`.
 */
class Collection extends \Magento\Sales\Model\ResourceModel\Report\Invoiced\Collection\Order
{
    private bool $appliedFilters = false;

    private readonly RequestInterface $request;

    public function __construct(
        EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        SalesReportResource $resource,
        ?AdapterInterface $connection = null,
        ?RequestInterface $request = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $resource,
            $connection
        );
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $this->request = $request ?? $om->get(RequestInterface::class);
    }

    protected function _beforeLoad(): self
    {
        if (!$this->appliedFilters) {
            $this->appliedFilters = true;

            $period = (string) $this->request->getParam('period', 'day');
            if (!in_array($period, ['day', 'month', 'year'], true)) {
                $period = 'day';
            }
            $from = (string) $this->request->getParam('from', date('Y-m-d', strtotime('-30 days')));
            $to   = (string) $this->request->getParam('to',   date('Y-m-d'));

            $this->setPeriod($period)->setDateRange($from, $to);
        }
        return parent::_beforeLoad();
    }
}
