<?php

declare(strict_types=1);

namespace Qoliber\NebulaStore\Model\DataProvider;

use Magento\Store\Model\StoreFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class StoreViewProvider implements DataProviderInterface
{
    public function __construct(
        private readonly StoreFactory $storeFactory
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $store = $this->storeFactory->create();
        $store->load((int) $entityId);

        if (!$store->getId()) {
            return [];
        }

        return [
            'store' => [
                'store_id' => $store->getId(),
                'group_id' => $store->getGroupId(),
                'name' => $store->getName(),
                'code' => $store->getCode(),
                'is_active' => $store->isActive() ? '1' : '0',
                'sort_order' => $store->getSortOrder(),
            ],
        ];
    }
}
