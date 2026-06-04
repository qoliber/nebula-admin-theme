<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Agreement;

class NewAction extends AbstractAgreement
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->initAgreement();

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData($this->createFormPayload(false));
        }

        return $this->createPage((string) __('New Condition'));
    }
}
