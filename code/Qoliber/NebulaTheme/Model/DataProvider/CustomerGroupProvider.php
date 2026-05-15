<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\DataProvider;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\ResourceModel\GroupExcludedWebsiteRepository;
use Qoliber\NebulaComponent\Api\FormDataProviderInterface;

class CustomerGroupProvider implements FormDataProviderInterface
{
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly GroupExcludedWebsiteRepository $excludedWebsiteRepository
    ) {
    }

    public function getData(array $config, array $params = []): array
    {
        $entityId = $params['entityId'] ?? null;

        if ($entityId === null) {
            return [];
        }

        $groupId = (int) $entityId;
        $group = $this->groupRepository->getById($groupId);

        // getCustomerGroupExcludedWebsites returns a flat array of website IDs, not website objects.
        $excludedWebsiteIds = array_map(
            static fn ($websiteId): string => (string) $websiteId,
            $this->excludedWebsiteRepository->getCustomerGroupExcludedWebsites($groupId)
        );

        return [
            'code' => $group->getCode(),
            'tax_class' => $group->getTaxClassId(),
            'customer_group_excluded_websites' => $excludedWebsiteIds,
        ];
    }
}
