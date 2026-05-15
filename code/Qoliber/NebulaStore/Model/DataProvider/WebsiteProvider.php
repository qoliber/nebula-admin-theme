<?php

declare(strict_types=1);

namespace Qoliber\NebulaStore\Model\DataProvider;

use Magento\Store\Model\WebsiteFactory;
use Qoliber\NebulaComponent\Api\DataProviderInterface;

class WebsiteProvider implements DataProviderInterface
{
    public function __construct(
        private readonly WebsiteFactory $websiteFactory
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $website = $this->websiteFactory->create();
        $website->load((int) $entityId);

        if (!$website->getId()) {
            return [];
        }

        return [
            'website' => [
                'website_id' => $website->getId(),
                'name' => $website->getName(),
                'code' => $website->getCode(),
                'sort_order' => $website->getSortOrder(),
                'default_group_id' => $website->getDefaultGroupId(),
                'is_default' => $website->getIsDefault(),
            ],
        ];
    }
}
