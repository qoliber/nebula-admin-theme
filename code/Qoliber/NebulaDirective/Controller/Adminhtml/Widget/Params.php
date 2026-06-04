<?php

declare(strict_types=1);

namespace Qoliber\NebulaDirective\Controller\Adminhtml\Widget;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action;
use Qoliber\NebulaDirective\Controller\Adminhtml\AbstractJson;
use Qoliber\NebulaDirective\Model\WidgetProvider;

class Params extends AbstractJson implements HttpGetActionInterface
{
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        private readonly Json $json,
        private readonly WidgetProvider $widgetProvider,
    ) {
        parent::__construct($context, $resultJsonFactory, $logger);
    }

    protected function executeAction(): array
    {
        $currentValues = [];
        $rawCurrentValues = (string) $this->getRequest()->getParam('current_values', '');

        if ($rawCurrentValues !== '') {
            try {
                $decoded = $this->json->unserialize($rawCurrentValues);
                if (is_array($decoded)) {
                    $currentValues = $decoded;
                }
            } catch (\InvalidArgumentException) {
                $currentValues = [];
            }
        }

        return $this->widgetProvider->getParams(
            (string) $this->getRequest()->getParam('type', ''),
            $currentValues
        );
    }
}
