<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Controller\Adminhtml\Menupin;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Qoliber\NebulaMenu\Model\PinnedItems;

class Reorder extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly PinnedItems $pinnedItems,
        private readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $order = $this->getRequest()->getParam('order');

        if (!$order || !is_string($order)) {
            return $result->setData(['success' => false]);
        }

        $orderedIds = json_decode($order, true);
        if (!is_array($orderedIds)) {
            return $result->setData(['success' => false]);
        }

        $this->pinnedItems->reorder($orderedIds);

        return $result->setData(['success' => true]);
    }
}
