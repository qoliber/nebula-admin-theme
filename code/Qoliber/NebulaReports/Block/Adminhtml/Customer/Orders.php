<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Customer;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

class Orders extends \Magento\Reports\Block\Adminhtml\Customer\Orders
{
    private const NEBULA_GRID_ID  = 'report_customer_orders';
    private const FILTER_TEMPLATE = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $filterBar = $layout->createBlock(
            Template::class,
            'nebula.report_customer_orders.filter',
            ['data' => [
                'template'     => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('Customer Orders Report'),
                'page_summary' => (string) __('Customers ranked by number of orders within the selected date range.'),
                'hide_period'  => true,
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
