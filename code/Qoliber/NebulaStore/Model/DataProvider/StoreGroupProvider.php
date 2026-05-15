<?php

declare(strict_types=1);

namespace Qoliber\NebulaStore\Model\DataProvider;

use Magento\Store\Model\GroupFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class StoreGroupProvider implements DataProviderInterface
{
    public function __construct(
        private readonly GroupFactory $groupFactory
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $group = $this->groupFactory->create();
        $group->load((int) $entityId);

        if (!$group->getId()) {
            return [];
        }

        return [
            'group' => [
                'group_id' => $group->getId(),
                'website_id' => $group->getWebsiteId(),
                'name' => $group->getName(),
                'code' => $group->getCode(),
                'root_category_id' => $group->getRootCategoryId(),
                'default_store_id' => $group->getDefaultStoreId(),
            ],
        ];
    }
}
