<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Controller\Adminhtml\Browser;

use Magento\Framework\App\Action\HttpPostActionInterface;

class Upload extends AbstractJson implements HttpPostActionInterface
{
    protected function executeAction(): array
    {
        return $this->mediaPicker->upload($this->getRequest()->getParam('path'));
    }
}
