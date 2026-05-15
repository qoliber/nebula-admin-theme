<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Block;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\Model\Menu\Config as MenuConfig;
use Magento\Backend\Model\Menu\Filter\IteratorFactory;
use Magento\Backend\Model\Menu\Item;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Route\ConfigInterface as RouteConfigInterface;
use Magento\Framework\Locale\ResolverInterface as LocaleResolverInterface;
use Qoliber\Nebula\Model\Config\Source\MenuPosition;
use Qoliber\NebulaMenu\Model\PinnedItems;

class Menu extends \Magento\Backend\Block\Menu
{
    public function __construct(
        Context $context,
        BackendUrlInterface $url,
        IteratorFactory $iteratorFactory,
        Session $authSession,
        MenuConfig $menuConfig,
        LocaleResolverInterface $localeResolver,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PinnedItems $pinnedItems,
        array $data = [],
        ?\Magento\Backend\Block\MenuItemChecker $menuItemChecker = null,
        ?\Magento\Backend\Block\AnchorRenderer $anchorRenderer = null,
        ?RouteConfigInterface $routeConfig = null
    ) {
        parent::__construct(
            $context,
            $url,
            $iteratorFactory,
            $authSession,
            $menuConfig,
            $localeResolver,
            $data,
            $menuItemChecker,
            $anchorRenderer,
            $routeConfig
        );
    }

    protected function _beforeToHtml(): self
    {
        $position = $this->scopeConfig->getValue('nebula/theme/menu_position') ?: MenuPosition::SIDEBAR;

        $expectedTemplate = $position === MenuPosition::TOP
            ? 'Magento_Backend::menu_horizontal.phtml'
            : 'Magento_Backend::menu.phtml';

        if ((string) $this->getTemplate() !== $expectedTemplate) {
            $this->setTemplate($expectedTemplate);
        }

        return parent::_beforeToHtml();
    }

    /**
     * The parent block caches its rendered HTML for a day keyed only on
     * admin_top_nav + active item + user id + locale. Pinning/unpinning saves
     * to user extra data but the cache key never changes, so the sidebar keeps
     * serving the stale HTML. Fold the current pinned-id list into the key so
     * a toggle invalidates exactly this user's entry.
     */
    public function getCacheKeyInfo()
    {
        $info = parent::getCacheKeyInfo();
        $info[] = 'pinned:' . md5(implode(',', $this->pinnedItems->getPinnedIds()));

        return $info;
    }

    /**
     * Map menu item IDs to Font Awesome icon classes.
     */
    private const MENU_ICONS = [
        'Magento_Backend::dashboard' => 'fa-solid fa-gauge-high',
        'Magento_Sales::sales' => 'fa-solid fa-cart-shopping',
        'Magento_Sales::sales_operation' => 'fa-solid fa-file-invoice-dollar',
        'Magento_Catalog::catalog' => 'fa-solid fa-boxes-stacked',
        'Magento_Catalog::catalog_products' => 'fa-solid fa-cube',
        'Magento_Catalog::catalog_categories' => 'fa-solid fa-folder-tree',
        'Magento_Catalog::inventory' => 'fa-solid fa-warehouse',
        'Magento_Customer::customer' => 'fa-solid fa-users',
        'Magento_Customer::customer_manage' => 'fa-solid fa-user',
        'Magento_Backend::marketing' => 'fa-solid fa-bullhorn',
        'Magento_CatalogRule::promo' => 'fa-solid fa-tags',
        'Magento_Backend::content' => 'fa-solid fa-pen-nib',
        'Magento_Backend::content_elements' => 'fa-solid fa-puzzle-piece',
        'Magento_Backend::media' => 'fa-solid fa-images',
        'Magento_Backend::design' => 'fa-solid fa-palette',
        'Magento_Reports::report' => 'fa-solid fa-chart-line',
        'Magento_Backend::stores' => 'fa-solid fa-store',
        'Magento_Backend::stores_settings' => 'fa-solid fa-sliders',
        'Magento_Tax::sales_tax' => 'fa-solid fa-receipt',
        'Magento_CurrencySymbol::system_currency' => 'fa-solid fa-coins',
        'Magento_Catalog::attributes' => 'fa-solid fa-list-check',
        'Magento_Backend::system' => 'fa-solid fa-gear',
        'Magento_Backend::system_tools' => 'fa-solid fa-screwdriver-wrench',
        'Magento_ImportExport::system_convert' => 'fa-solid fa-arrows-rotate',
        'Magento_User::acl' => 'fa-solid fa-shield-halved',
        'Magento_Backend::system_other_settings' => 'fa-solid fa-ellipsis',
        'Magento_Marketplace::partners' => 'fa-solid fa-handshake',
    ];

    /**
     * Get menu tree + pinned items data for the template.
     *
     * @return array{items: list<array>, activeParents: list<string>, activeId: string, pinnedItems: list<array>, pinnedIds: list<string>, pinUrl: string}
     */
    public function getNebulaMenuData(): array
    {
        $menu = $this->getMenuModel();
        $activeItem = $this->getActiveItemModel();
        $activeId = $activeItem ? $activeItem->getId() : '';

        // Fallback: if Magento's resolver couldn't find the active item,
        // match current request path against menu item actions ourselves
        if ($activeId === '') {
            $activeId = $this->resolveActiveItemByRequest($menu);
        }

        $activeParents = [];
        if ($activeId) {
            foreach ($menu->getParentItems($activeId) as $parent) {
                $activeParents[] = $parent->getId();
            }
            // Also include the active item itself if it's a parent section
            $resolvedItem = $menu->get($activeId);
            if ($resolvedItem && $resolvedItem->hasChildren()) {
                $activeParents[] = $activeId;
            }
        }

        $items = $this->buildMenuTree($menu, $activeId, $activeParents);

        $pinnedIds = $this->pinnedItems->getPinnedIds();

        // Collect pinned item data from the full tree
        $pinnedItems = [];
        $allLeafs = $this->collectLeafItems($items);
        foreach ($pinnedIds as $pinnedId) {
            if (isset($allLeafs[$pinnedId])) {
                $pinnedItems[] = $allLeafs[$pinnedId];
            }
        }

        $pinUrl = $this->getUrl('nebula/menupin', ['_nosecret' => true]);

        return [
            'items' => $items,
            'activeParents' => $activeParents,
            'activeId' => $activeId,
            'pinnedItems' => $pinnedItems,
            'pinnedIds' => $pinnedIds,
            'pinUrl' => $pinUrl,
        ];
    }

    /**
     * Match the current request against menu item actions to find the active item.
     * Handles the case where Magento's built-in resolver can't match re-created Nebula menu items.
     */
    private function resolveActiveItemByRequest(\Magento\Backend\Model\Menu $menu): string
    {
        $request = $this->getRequest();
        $requestParts = [
            $request->getModuleName(),
            $request->getControllerName(),
            $request->getActionName(),
        ];

        // Magento admin URLs route as <frontName>/<route>/<controller>/<action>.
        // `$request->getModuleName()` returns the *route* segment — which is
        // usually "admin" (e.g. "admin/cache/index"). The matching menu XML,
        // however, declares actions with the logical *area* prefix
        // "adminhtml/..." (e.g. "adminhtml/cache"). Normalize both sides so
        // the first segment is a shared sentinel before comparing.
        $fullPath = $this->normalizeAdminPath(implode('/', $requestParts));
        $controllerPath = $this->normalizeAdminPath($requestParts[0] . '/' . $requestParts[1]);

        $bestMatch = '';
        $bestMatchLength = 0;

        $this->walkMenu($menu, function (Item $item) use ($fullPath, $controllerPath, &$bestMatch, &$bestMatchLength) {
            $action = $this->normalizeAdminPath(trim((string) $item->getAction(), '/'));
            if ($action === '') {
                return;
            }

            // Exact match on full path or controller path
            if ($action === $fullPath || $action === $controllerPath) {
                if (strlen($action) > $bestMatchLength) {
                    $bestMatch = $item->getId();
                    $bestMatchLength = strlen($action);
                }
                return;
            }

            // Prefix match: "catalog/product" matches "catalog/product_attribute"
            if (str_starts_with($controllerPath, $action) || str_starts_with($fullPath, $action)) {
                if (strlen($action) > $bestMatchLength) {
                    $bestMatch = $item->getId();
                    $bestMatchLength = strlen($action);
                }
            }
        });

        return $bestMatch;
    }

    /**
     * Collapse the many variants of the admin route prefix — "admin",
     * "adminhtml", the configured admin frontName — onto a single sentinel
     * so request paths and menu action attributes compare apples-to-apples.
     */
    private function normalizeAdminPath(string $path): string
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            return '';
        }
        $parts = explode('/', $path, 2);
        if (in_array($parts[0], ['adminhtml', 'admin'], true)) {
            $parts[0] = 'adminhtml';
        }
        return implode('/', $parts);
    }

    /**
     * Recursively walk all menu items and call a callback for each leaf.
     */
    private function walkMenu(iterable $items, callable $callback): void
    {
        foreach ($items as $item) {
            /** @var \Magento\Backend\Model\Menu\Item $item */
            if ($item->hasChildren()) {
                $this->walkMenu($item->getChildren(), $callback);
            } else {
                $callback($item);
            }
        }
    }

    /**
     * Recursively collect all leaf (non-parent) items as a flat map.
     */
    private function collectLeafItems(array $items): array
    {
        $leafs = [];
        foreach ($items as $item) {
            if (!$item['hasChildren']) {
                $leafs[$item['id']] = $item;
            }
            if (!empty($item['children'])) {
                $leafs = array_merge($leafs, $this->collectLeafItems($item['children']));
            }
        }

        return $leafs;
    }

    /**
     * Recursively build menu tree as plain arrays.
     */
    private function buildMenuTree(iterable $items, string $activeId, array $activeParents): array
    {
        $result = [];

        foreach ($items as $menuItem) {
            /** @var \Magento\Backend\Model\Menu\Item $menuItem */
            $itemId = $menuItem->getId();
            $hasChildren = $menuItem->hasChildren();

            $node = [
                'id' => $itemId,
                'title' => (string) $menuItem->getTitle(),
                'url' => $menuItem->getUrl(),
                'hasChildren' => $hasChildren,
                'isActive' => $itemId === $activeId,
                'isActiveParent' => in_array($itemId, $activeParents),
                'icon' => self::MENU_ICONS[$itemId] ?? null,
                'children' => [],
            ];

            if ($hasChildren) {
                $node['children'] = $this->buildMenuTree(
                    $menuItem->getChildren(),
                    $activeId,
                    $activeParents
                );
            }

            $result[] = $node;
        }

        return $result;
    }
}
