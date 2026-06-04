<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRate;

class NewAction extends AbstractTaxRate
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->registerFormDataFromSession();

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData($this->createFormPayload(false));
        }

        return $this->createPage((string) __('New Tax Rate'));
    }
}
