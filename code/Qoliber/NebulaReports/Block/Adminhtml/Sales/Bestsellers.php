<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Sales;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

class Bestsellers extends \Magento\Reports\Block\Adminhtml\Sales\Bestsellers
{
    private const NEBULA_GRID_ID  = 'report_sales_bestsellers';
    private const FILTER_TEMPLATE = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $filterBar = $layout->createBlock(
            Template::class,
            'nebula.report_sales_bestsellers.filter',
            ['data' => [
                'template'     => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('Best-Selling Products'),
                'page_summary' => (string) __('Top-ordered products per period. Refresh statistics under Reports → Statistics if the data feels stale.'),
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
