<?php

declare(strict_types=1);

namespace Qoliber\NebulaStore\Model\DataProvider;

use Magento\Store\Model\StoreManagerInterface;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class DesignConfigProvider implements DataProviderInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $items = [];

        $items[] = [
            'entity_id' => 0,
            'scope' => 'default',
            'scope_id' => 0,
            'store_website_id' => '',
            'store_group_id' => '',
            'store_id' => '',
        ];

        foreach ($this->storeManager->getWebsites() as $website) {
            $items[] = [
                'entity_id' => 'website_' . $website->getId(),
                'scope' => 'websites',
                'scope_id' => $website->getId(),
                'store_website_id' => $website->getName(),
                'store_group_id' => '',
                'store_id' => '',
            ];

            foreach ($website->getGroups() as $group) {
                foreach ($group->getStores() as $store) {
                    $items[] = [
                        'entity_id' => 'store_' . $store->getId(),
                        'scope' => 'stores',
                        'scope_id' => $store->getId(),
                        'store_website_id' => $website->getName(),
                        'store_group_id' => $group->getName(),
                        'store_id' => $store->getName(),
                    ];
                }
            }
        }

        return [
            'items' => $items,
            'totalCount' => count($items),
        ];
    }
}
