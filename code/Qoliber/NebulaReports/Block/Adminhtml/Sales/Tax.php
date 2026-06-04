<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Sales;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

/**
 * Drop-in replacement for the stock Sales\Tax report container block.
 *
 * The parent Container's `_prepareLayout()` still creates the legacy grid
 * + filter-form child blocks so the controller's
 * `$layout->getBlock('adminhtml_sales_tax.grid')` setter chain in
 * `_initReportAction(...)` keeps resolving. We override `_toHtml()` to
 * emit a Nebula grid + filter bar instead of the legacy markup.
 */
class Tax extends \Magento\Reports\Block\Adminhtml\Sales\Tax
{
    private const NEBULA_GRID_ID  = 'report_sales_tax';
    private const FILTER_TEMPLATE = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $filterBar = $layout->createBlock(
            Template::class,
            'nebula.report_sales_tax.filter',
            ['data' => [
                'template'  => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('Tax Report'),
                'page_summary' => (string) __('Order taxes grouped by tax rate. Refresh statistics under Reports → Statistics if the data feels stale.'),
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
