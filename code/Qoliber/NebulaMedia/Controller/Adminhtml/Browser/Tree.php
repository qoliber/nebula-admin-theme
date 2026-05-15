<?php

declare(strict_types=1);

namespace Qoliber\NebulaMedia\Controller\Adminhtml\Browser;

use Magento\Framework\App\Action\HttpGetActionInterface;

class Tree extends AbstractJson implements HttpGetActionInterface
{
    protected function executeAction(): array
    {
        return [
            'tree' => $this->mediaPicker->getTree(),
        ];
    }
}
