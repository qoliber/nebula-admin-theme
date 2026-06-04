<?php

declare(strict_types=1);

namespace Qoliber\NebulaSystem\Controller\Adminhtml\Stores\Terms;

use Magento\Framework\Controller\ResultInterface;
use Qoliber\NebulaSystem\Controller\Adminhtml\System\AbstractPlaceholder;

class Conditions extends AbstractPlaceholder
{
    public function execute(): ResultInterface
    {
        return $this->resultRedirectFactory->create()->setPath('nebula/agreement/index');
    }

    protected function getPageCode(): string
    {
        return 'storestermsconditions';
    }
}
