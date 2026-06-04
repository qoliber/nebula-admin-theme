<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Shopcart;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

/**
 * Drop-in replacement for the stock Products-in-Carts container block.
 *
 * The stock controller (Magento\Reports\Controller\Adminhtml\Report\Shopcart\Product)
 * does `$this->_addContent($layout->createBlock(ParentClass::class))` with no
 * layout name, so removing the stock grid via `<referenceBlock remove>` is
 * impossible. A DI preference (etc/di.xml) re-routes that createBlock call
 * here, and we emit a Nebula header bar (title + summary, no date filter)
 * plus the Nebula grid — visually consistent with the other reports.
 */
class Product extends \Magento\Reports\Block\Adminhtml\Shopcart\Product
{
    private const NEBULA_GRID_ID  = 'report_shopcart_product';
    private const FILTER_TEMPLATE = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $header = $layout->createBlock(
            Template::class,
            'nebula.' . self::NEBULA_GRID_ID . '.header',
            ['data' => [
                'template'     => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('Products in Carts'),
                'page_summary' => (string) __('Products that currently sit in open shopping carts across active customers.'),
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
