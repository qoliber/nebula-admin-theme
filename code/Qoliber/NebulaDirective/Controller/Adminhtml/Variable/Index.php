<?php

declare(strict_types=1);

namespace Qoliber\NebulaDirective\Controller\Adminhtml\Variable;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action;
use Qoliber\NebulaDirective\Controller\Adminhtml\AbstractJson;
use Qoliber\NebulaDirective\Model\VariableProvider;

class Index extends AbstractJson implements HttpGetActionInterface
{
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        private readonly VariableProvider $variableProvider,
    ) {
        parent::__construct($context, $resultJsonFactory, $logger);
    }

    protected function executeAction(): array
    {
        return $this->variableProvider->getAll();
    }
}
