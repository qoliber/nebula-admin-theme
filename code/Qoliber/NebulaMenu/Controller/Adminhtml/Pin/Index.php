<?php

declare(strict_types=1);

namespace Qoliber\NebulaMenu\Controller\Adminhtml\Pin;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Qoliber\NebulaMenu\Model\PinnedItems;

class Index extends Action implements HttpPostActionInterface
{
    /** @var string[] */
    protected $_publicActions = ['index'];

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
            return $result->setData(['success' => false, 'message' => 'Missing item_id']);
        }

        $isPinned = $this->pinnedItems->toggle($menuItemId);

        return $result->setData([
            'success' => true,
            'pinned' => $isPinned,
            'item_id' => $menuItemId,
        ]);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Magento_Backend::admin');
    }
}
