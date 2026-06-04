<?php

declare(strict_types=1);

namespace Qoliber\NebulaDirective\Controller\Adminhtml\Widget;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action;
use Qoliber\NebulaDirective\Controller\Adminhtml\AbstractJson;
use Qoliber\NebulaDirective\Model\WidgetProvider;

class Types extends AbstractJson implements HttpGetActionInterface
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
        return $this->widgetProvider->getTypes();
    }
}
