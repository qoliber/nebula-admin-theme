<?php

declare(strict_types=1);

namespace Qoliber\NebulaDirective\Controller\Adminhtml\Widget;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action;
use Qoliber\NebulaDirective\Controller\Adminhtml\AbstractJson;
use Qoliber\NebulaDirective\Model\WidgetProvider;

class Build extends AbstractJson implements HttpPostActionInterface
{
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        private readonly WidgetProvider $widgetProvider,
    ) {
        parent::__construct($context, $resultJsonFactory, $logger);
    }

    protected function executeAction(): array
    {
        $type = (string) $this->getRequest()->getPost('widget_type', '');
        $parameters = (array) $this->getRequest()->getPost('parameters', []);

        return $this->widgetProvider->buildDirective($type, $parameters);
    }
}
