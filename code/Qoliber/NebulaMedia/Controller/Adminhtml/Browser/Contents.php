<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Controller\Adminhtml\Browser;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Contents extends AbstractJson implements HttpGetActionInterface
{
    protected function executeAction(): array
    {
        return $this->mediaPicker->getContents($this->getRequest()->getParam('path'));
    }
}
