<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Sales;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

class Shipping extends \Magento\Reports\Block\Adminhtml\Sales\Shipping
{
    private const NEBULA_GRID_ID  = 'report_sales_shipping';
    private const FILTER_TEMPLATE = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $filterBar = $layout->createBlock(
            Template::class,
            'nebula.report_sales_shipping.filter',
            ['data' => [
                'template'     => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('Shipping Report'),
                'page_summary' => (string) __('Sales shipping totals per period and carrier/method. Refresh statistics under Reports → Statistics if the data feels stale.'),
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
