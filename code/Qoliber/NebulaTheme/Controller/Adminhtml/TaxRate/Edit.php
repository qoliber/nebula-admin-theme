<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\TaxRate;

class Edit extends AbstractTaxRate
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->registerFormDataFromSession();
        $rate = $this->initRate(true);

        if ($rate === null) {
            if ($this->isAjaxRequest()) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) __('We can\'t find that tax rate.'),
                    'redirectUrl' => $this->getUrl('tax/rate/index'),
                ]);
            }

            return $this->resultRedirectFactory->create()->setPath('tax/rate/index');
        }

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData(
                $this->createFormPayload(true, (string) $rate->getCode())
            );
        }

        return $this->createPage((string) $rate->getCode());
    }
}
