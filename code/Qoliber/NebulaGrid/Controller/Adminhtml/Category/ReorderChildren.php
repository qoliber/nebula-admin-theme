<?php

declare(strict_types=1);

namespace Qoliber\NebulaGrid\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class ReorderChildren extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        $body = json_decode((string) $this->getRequest()->getContent(), true);
        $parentId = (int) ($body['parent_id'] ?? 0);
        $ids = array_values(array_filter(array_map('intval', (array) ($body['ids'] ?? []))));

        if ($parentId < 0 || empty($ids)) {
            return $result->setData(['success' => false, 'message' => 'Invalid parameters.']);
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_category_entity');

        try {
            $connection->beginTransaction();
            foreach ($ids as $position => $entityId) {
                $connection->update(
                    $table,
                    ['position' => $position + 1],
                    ['entity_id = ?' => $entityId, 'parent_id = ?' => $parentId]
                );
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->logger->error('Nebula category reorder failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData(['success' => false, 'message' => 'Could not save the order.']);
        }

        return $result->setData(['success' => true]);
    }

    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Magento_Catalog::categories');
    }
}
