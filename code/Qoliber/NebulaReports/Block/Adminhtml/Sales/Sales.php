<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Sales;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

/**
 * Drop-in replacement for the stock Sales Report container block.
 *
 * The stock controller (Sales::execute()) does:
 *     $gridBlock       = $layout->getBlock('adminhtml_sales_sales.grid');
 *     $filterFormBlock = $layout->getBlock('grid.filter.form');
 *     $this->_initReportAction([$gridBlock, $filterFormBlock]);
 *
 * Both child blocks are auto-created by the parent Container's _prepareLayout()
 * even when we override _toHtml(), so the controller's getBlock() calls keep
 * resolving. We just bypass the rendering and emit a Nebula grid + a small
 * date-range filter bar instead.
 */
class Sales extends \Magento\Reports\Block\Adminhtml\Sales\Sales
{
    private const NEBULA_GRID_ID         = 'report_sales_sales';
    private const FILTER_TEMPLATE        = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $filterBar = $layout->createBlock(
            Template::class,
            'nebula.report_sales_sales.filter',
            ['data' => [
                'template'     => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('Orders Report'),
                'page_summary' => (string) __('Aggregated sales totals per period — excludes canceled orders. Refresh statistics under Reports → Statistics if the data feels stale.'),
            ]]
        );

        $grid = $layout->createBlock(
            NebulaGrid::class,
            'nebula.' . self::NEBULA_GRID_ID,
            ['data' => ['grid_id' => self::NEBULA_GRID_ID]]
        );

        return $filterBar->toHtml() . $grid->toHtml();
    }
}
