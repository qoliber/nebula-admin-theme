<?php

declare(strict_types=1);

namespace Qoliber\NebulaStore\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;

/**
 * Builds the Design Configuration scope tree:
 *   ⚙ Default  →  🌐 Website  →  🏪 Group (label only)  →  · Store View
 *
 * Magento's design_config scope is one of: 'default', 'websites', 'stores'
 * (where 'stores' actually means store *view*). There is no group-level
 * design scope, so group rows render as non-clickable labels for context.
 *
 * Each scope row also surfaces the *active* (effective, post-inheritance)
 * frontend theme name so merchants can see which theme each store is
 * actually using without having to click into the edit page.
 */
class DesignConfigTree implements ArgumentInterface
{
    private const SCOPE_DEFAULT     = 'default';
    private const SCOPE_WEBSITES    = 'websites';
    private const SCOPE_STORES      = 'stores';
    private const THEME_CONFIG_PATH = 'design/theme/theme_id';

    /** @var array<int, string>|null */
    private ?array $themeMap = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ThemeCollectionFactory $themeCollectionFactory
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultScope(): array
    {
        $themeId = (int) $this->scopeConfig->getValue(self::THEME_CONFIG_PATH);

        return [
            'name'        => (string) __('Default'),
            'description' => (string) __('Applies to all websites and store views (global scope)'),
            'theme_name'  => $this->resolveThemeName($themeId),
            'edit_url'    => $this->urlBuilder->getUrl(
                'theme/design_config/edit',
                ['scope' => self::SCOPE_DEFAULT, 'scope_id' => 0]
            ),
        ];
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
            $groupId   = (int) $group->getId();
            $websiteId = (int) $group->getWebsiteId();
            if ($groupId === 0 || !isset($tree[$websiteId])) {
                continue;
            }
            $tree[$websiteId]['groups'][$groupId] = $this->buildGroupNode($group);
        }

        foreach ($this->storeManager->getStores(true, false) as $store) {
            $storeId   = (int) $store->getId();
            $groupId   = (int) $store->getStoreGroupId();
            $websiteId = (int) $store->getWebsiteId();
            if ($storeId === 0 || !isset($tree[$websiteId]['groups'][$groupId])) {
                continue;
            }
            $tree[$websiteId]['groups'][$groupId]['stores'][$storeId] = $this->buildStoreNode($store);
        }

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

    /**
     * @return array<string, mixed>
     */
    private function buildWebsiteNode(WebsiteInterface $website): array
    {
        $websiteId = (int) $website->getId();
        $themeId   = (int) $this->scopeConfig->getValue(
            self::THEME_CONFIG_PATH,
            ScopeInterface::SCOPE_WEBSITES,
            (string) $website->getCode()
        );

        return [
            'id'         => $websiteId,
            'name'       => (string) $website->getName(),
            'code'       => (string) $website->getCode(),
            'is_default' => (int) $website->getIsDefault() === 1,
            'theme_name' => $this->resolveThemeName($themeId),
            'edit_url'   => $this->urlBuilder->getUrl(
                'theme/design_config/edit',
                ['scope' => self::SCOPE_WEBSITES, 'scope_id' => $websiteId]
            ),
            'groups'     => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGroupNode(GroupInterface $group): array
    {
        return [
            'id'     => (int) $group->getId(),
            'name'   => (string) $group->getName(),
            'stores' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStoreNode(StoreInterface $store): array
    {
        $storeId = (int) $store->getId();
        $themeId = (int) $this->scopeConfig->getValue(
            self::THEME_CONFIG_PATH,
            ScopeInterface::SCOPE_STORES,
            (string) $store->getCode()
        );

        return [
            'id'         => $storeId,
            'name'       => (string) $store->getName(),
            'code'       => (string) $store->getCode(),
            'is_active'  => (int) $store->getIsActive() === 1,
            'theme_name' => $this->resolveThemeName($themeId),
            'edit_url'   => $this->urlBuilder->getUrl(
                'theme/design_config/edit',
                ['scope' => self::SCOPE_STORES, 'scope_id' => $storeId]
            ),
        ];
    }

    private function resolveThemeName(int $themeId): string
    {
        if ($themeId === 0) {
            return '';
        }
        return $this->getThemeMap()[$themeId] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function getThemeMap(): array
    {
        if ($this->themeMap === null) {
            $this->themeMap = [];
            $collection = $this->themeCollectionFactory->create();
            $collection->addFieldToFilter('area', 'frontend');
            foreach ($collection as $theme) {
                $this->themeMap[(int) $theme->getId()] = (string) $theme->getThemeTitle();
            }
        }
        return $this->themeMap;
    }
}
