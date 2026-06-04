<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRate;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends AbstractTaxRate implements HttpGetActionInterface
{
    public function execute(): \Magento\Backend\Model\View\Result\Page
    {
        return $this->createPage((string) __('Tax Zones and Rates'));
    }
}
