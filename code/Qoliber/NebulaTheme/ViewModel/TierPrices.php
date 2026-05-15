<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ViewModel for the `tier_prices` snippet. Exposes customer groups +
 * websites for the tier-price editor.
 */
class TierPrices implements ArgumentInterface
{
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return list<GroupInterface>
     */
    public function getCustomerGroups(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();

        return array_values($this->groupRepository->getList($searchCriteria)->getItems());
    }

    /**
     * @return list<WebsiteInterface>
     */
    public function getWebsites(): array
    {
        return array_values($this->storeManager->getWebsites());
    }
}
