<?php

declare(strict_types=1);

namespace Qoliber\NebulaReports\Block\Adminhtml\Customer;

use Magento\Backend\Block\Template;
use Qoliber\NebulaGrid\Block\Grid as NebulaGrid;

/**
 * Drop-in replacement for the stock Customer\Accounts report container.
 *
 * Customer reports don't run through `_initReportAction()` (the controller
 * just calls `_initAction()->_setActiveMenu()` and renders), so the parent
 * Container's `_prepareLayout()` having no setter-chain side effects is
 * fine. We just emit the Nebula grid + date filter bar instead of the
 * legacy markup.
 */
class Accounts extends \Magento\Reports\Block\Adminhtml\Customer\Accounts
{
    private const NEBULA_GRID_ID  = 'report_customer_accounts';
    private const FILTER_TEMPLATE = 'Qoliber_NebulaReports::snippet/sales_report_filter.phtml';

    protected function _toHtml(): string
    {
        $layout = $this->getLayout();

        $filterBar = $layout->createBlock(
            Template::class,
            'nebula.report_customer_accounts.filter',
            ['data' => [
                'template'     => self::FILTER_TEMPLATE,
                'page_title'   => (string) __('New Accounts Report'),
                'page_summary' => (string) __('New customer registrations in the selected date range. The period selector is ignored for this report.'),
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
