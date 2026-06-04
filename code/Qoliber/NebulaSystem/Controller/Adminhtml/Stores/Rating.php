<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Stores;

use Magento\Framework\Controller\ResultInterface;
use Qoliber\NebulaSystem\Controller\Adminhtml\System\AbstractPlaceholder;

class Rating extends AbstractPlaceholder
{
    public function execute(): ResultInterface
    {
        return $this->resultRedirectFactory->create()->setPath('review/rating/');
    }

    protected function getPageCode(): string
    {
        return 'storesrating';
    }
}
