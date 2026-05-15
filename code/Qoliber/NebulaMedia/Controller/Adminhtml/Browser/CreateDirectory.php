<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Controller\Adminhtml\Browser;

use Magento\Framework\App\Action\HttpPostActionInterface;

class CreateDirectory extends AbstractJson implements HttpPostActionInterface
{
    protected function executeAction(): array
    {
        $name = trim((string) $this->getRequest()->getParam('name'));

        return $this->mediaPicker->createDirectory($this->getRequest()->getParam('path'), $name);
    }
}
