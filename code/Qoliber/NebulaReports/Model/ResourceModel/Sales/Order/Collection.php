<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Model\ResourceModel\Sales\Order;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ConfigFactory;
use Magento\Sales\Model\ResourceModel\Report as SalesReportResource;

/**
 * Nebula wrapper around the stock Sales Order report collection.
 *
 * The legacy report block built up its filter from a base64-serialised
 * `filter[]` query param. Nebula uses simple, readable query params instead:
 *
 *   ?period=day    (day | month | year, default: day)
 *   ?from=YYYY-MM-DD   (default: 30 days ago)
 *   ?to=YYYY-MM-DD     (default: today)
 *
 * The date-range filter bar (rendered above the grid by
 * Qoliber\NebulaReports\Block\Adminhtml\Sales\Sales) submits these as a
 * plain GET form, and this collection reads them in _beforeLoad().
 *
 * Canceled orders are excluded by default — same as the stock controller's
 * pre-load filter shim.
 */
class Collection extends \Magento\Sales\Model\ResourceModel\Report\Order\Collection
{
    private bool $appliedFilters = false;

    private readonly ConfigFactory $configFactory;
    private readonly RequestInterface $request;

    /**
     * Custom dependencies live AFTER the standard ones so we don't shift the
     * argument-type signature Magento's DI compiler validates against the
     * parent. ConfigFactory + RequestInterface are appended; they default to
     * null and resolve via the ObjectManager if (rarely) instantiated outside
     * DI, keeping back-compat with the parent's constructor.
     */
    public function __construct(
        EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        SalesReportResource $resource,
        ?AdapterInterface $connection = null,
        ?ConfigFactory $configFactory = null,
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
        $this->configFactory = $configFactory ?? $om->get(ConfigFactory::class);
        $this->request       = $request       ?? $om->get(RequestInterface::class);
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
            $this->addOrderStatusFilter($this->getNonCanceledStatuses());
        }
        return parent::_beforeLoad();
    }

    /**
     * @return string[]
     */
    private function getNonCanceledStatuses(): array
    {
        $orderConfig      = $this->configFactory->create();
        $canceledStatuses = $orderConfig->getStateStatuses(Order::STATE_CANCELED);
        $statusValues     = [];
        foreach (array_keys($orderConfig->getStatuses()) as $code) {
            if (!isset($canceledStatuses[$code])) {
                $statusValues[] = $code;
            }
        }
        return $statusValues;
    }
}
