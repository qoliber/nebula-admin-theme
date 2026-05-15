<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Cms\Api\BlockRepositoryInterface;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class CmsBlockProvider implements DataProviderInterface
{
    public function __construct(
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $block = $this->blockRepository->getById((string) $entityId);

        return [
            'block_id' => $block->getId(),
            'title' => $block->getTitle(),
            'identifier' => $block->getIdentifier(),
            'is_active' => $block->isActive() ? '1' : '0',
            'store_id' => $block->getStoreId(),
            'content' => $block->getContent(),
        ];
    }
}
