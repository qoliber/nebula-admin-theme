<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Shopcart;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

/**
 * Drop-in replacement for the stock Abandoned-Carts container block.
 * See Qoliber\NebulaReports\Block\Adminhtml\Shopcart\Product for the rationale —
 * same _addContent() pattern, same DI-preference trick. Abandoned-Carts also
 * has no date window in the stock report (it lists *currently* abandoned carts),
 * so we hide the dates row too.
 */
class Abandoned extends \Magento\Reports\Block\Adminhtml\Shopcart\Abandoned
{
    private const NEBULA_GRID_ID  = 'report_shopcart_abandoned';
    private const FILTER_TEMPLATE = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $header = $layout->createBlock(
            Template::class,
            'nebula.' . self::NEBULA_GRID_ID . '.header',
            ['data' => [
                'template'     => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('Abandoned Carts'),
                'page_summary' => (string) __('Carts that have items but no order — sortable by customer, items, subtotal, and last update.'),
                'hide_dates'   => true,
            ]]
        );

        $grid = $layout->createBlock(
            NebulaGrid::class,
            'nebula.' . self::NEBULA_GRID_ID,
            ['data' => ['grid_id' => self::NEBULA_GRID_ID]]
        );

        return $header->toHtml() . $grid->toHtml();
    }
}
