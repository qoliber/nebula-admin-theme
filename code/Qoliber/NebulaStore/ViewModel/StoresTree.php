<?php

declare(strict_types=1);

namespace Qoliber\NebulaStore\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the Website → Store Group → Store View tree for the redesigned
 * "Stores" admin page. Replaces the flat Website/Store/Store View grid.
 *
 * Each node carries its own edit URL plus, for parent nodes, a "new child"
 * URL that pre-fills the parent ID — so creating a store under a specific
 * website (or a view under a specific store) is a single click.
 */
class StoresTree implements ArgumentInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTree(): array
    {
        /** @var array<int, array<string, mixed>> $tree */
        $tree = [];

        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int) $website->getId();
            if ($websiteId === 0) {
                continue;
            }
            $tree[$websiteId] = $this->buildWebsiteNode($website);
        }

        foreach ($this->storeManager->getGroups() as $group) {
            $groupId = (int) $group->getId();
            $websiteId = (int) $group->getWebsiteId();
            if ($groupId === 0 || !isset($tree[$websiteId])) {
                continue;
            }
            $tree[$websiteId]['groups'][$groupId] = $this->buildGroupNode(
                $group,
                $tree[$websiteId]['default_group_id'] === $groupId
            );
        }

        foreach ($this->storeManager->getStores(true, false) as $store) {
            $storeId = (int) $store->getId();
            $groupId = (int) $store->getStoreGroupId();
            $websiteId = (int) $store->getWebsiteId();
            if ($storeId === 0 || !isset($tree[$websiteId]['groups'][$groupId])) {
                continue;
            }
            $isDefault = $tree[$websiteId]['groups'][$groupId]['default_store_id'] === $storeId;
            $tree[$websiteId]['groups'][$groupId]['stores'][$storeId] = $this->buildStoreNode(
                $store,
                $isDefault
            );
        }

        // Re-index nested arrays so JSON encodes them as arrays, not objects.
        foreach ($tree as &$website) {
            foreach ($website['groups'] as &$group) {
                $group['stores'] = array_values($group['stores']);
            }
            unset($group);
            $website['groups'] = array_values($website['groups']);
        }
        unset($website);

        return array_values($tree);
    }

    public function getNewWebsiteUrl(): string
    {
        return $this->urlBuilder->getUrl('adminhtml/system_store/newWebsite');
    }

    /**
     * URL to the "new store group" form with no parent pre-selected.
     * Magento's newGroup controller renders the form with a website
     * picker, so the merchant can choose the parent there.
     */
    public function getNewGroupUrl(): string
    {
        return $this->urlBuilder->getUrl('adminhtml/system_store/newGroup');
    }

    /**
     * URL to the "new store view" form with no parent pre-selected.
     * Magento's newStore controller renders the form with a store-group
     * picker, so the merchant chooses the parent there.
     */
    public function getNewStoreUrl(): string
    {
        return $this->urlBuilder->getUrl('adminhtml/system_store/newStore');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWebsiteNode(WebsiteInterface $website): array
    {
        $websiteId = (int) $website->getId();

        return [
            'id'               => $websiteId,
            'name'             => (string) $website->getName(),
            'code'             => (string) $website->getCode(),
            'is_default'       => (int) $website->getIsDefault() === 1,
            'default_group_id' => (int) $website->getDefaultGroupId(),
            'edit_url'         => $this->urlBuilder->getUrl(
                'adminhtml/system_store/editWebsite',
                ['website_id' => $websiteId]
            ),
            'new_group_url'    => $this->urlBuilder->getUrl(
                'adminhtml/system_store/newGroup',
                ['website_id' => $websiteId]
            ),
            'groups'           => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGroupNode(GroupInterface $group, bool $isDefault): array
    {
        $groupId = (int) $group->getId();

        return [
            'id'               => $groupId,
            'name'             => (string) $group->getName(),
            'is_default'       => $isDefault,
            'default_store_id' => (int) $group->getDefaultStoreId(),
            'edit_url'         => $this->urlBuilder->getUrl(
                'adminhtml/system_store/editGroup',
                ['group_id' => $groupId]
            ),
            'new_store_url'    => $this->urlBuilder->getUrl(
                'adminhtml/system_store/newStore',
                ['group_id' => $groupId]
            ),
            'stores'           => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStoreNode(StoreInterface $store, bool $isDefault): array
    {
        $storeId = (int) $store->getId();

        return [
            'id'         => $storeId,
            'name'       => (string) $store->getName(),
            'code'       => (string) $store->getCode(),
            'is_active'  => (int) $store->getIsActive() === 1,
            'is_default' => $isDefault,
            'edit_url'   => $this->urlBuilder->getUrl(
                'adminhtml/system_store/editStore',
                ['store_id' => $storeId]
            ),
        ];
    }
}
