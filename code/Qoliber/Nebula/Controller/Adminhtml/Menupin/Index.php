<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Controller\Adminhtml\Menupin;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Qoliber\NebulaMenu\Model\PinnedItems;

class Index extends Action implements HttpPostActionInterface
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
        $menuItemId = $this->getRequest()->getParam('item_id');

        if (!$menuItemId) {
            return $result->setData(['success' => false]);
        }

        $isPinned = $this->pinnedItems->toggle($menuItemId);

        return $result->setData([
            'success' => true,
            'pinned' => $isPinned,
            'item_id' => $menuItemId,
        ]);
    }
}
