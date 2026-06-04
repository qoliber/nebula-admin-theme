<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRule;

class NewAction extends AbstractTaxRule
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->registerFormDataFromSession();

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData($this->createFormPayload(false));
        }

        return $this->createPage((string) __('New Tax Rule'));
    }
}
