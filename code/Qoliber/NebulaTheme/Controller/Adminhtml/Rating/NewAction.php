<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Rating;

class NewAction extends AbstractRating
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $this->initEntityId();

        if ($this->isAjaxRequest()) {
            return $this->resultJsonFactory->create()->setData($this->createFormPayload(false));
        }

        return $this->createPage((string) __('New Rating'));
    }
}
