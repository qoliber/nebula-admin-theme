<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Controller\Adminhtml\Agreement;

class Index extends AbstractAgreement
{
    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        return $this->createPage((string) __('Terms and Conditions'));
    }
}
