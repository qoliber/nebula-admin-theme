<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Controller\Adminhtml\Browser;

use Magento\Framework\App\Action\HttpPostActionInterface;

class DeleteFile extends AbstractJson implements HttpPostActionInterface
{
    protected function executeAction(): array
    {
        return $this->mediaPicker->deleteFile(
            (string) $this->getRequest()->getParam('file'),
            $this->getRequest()->getParam('path')
        );
    }
}
