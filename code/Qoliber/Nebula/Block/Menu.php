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
use Qoliber\Nebula\Model\Menu\MenuIconRegistry;
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
        private readonly MenuIconRegistry $iconRegistry,
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
     * Render the icon HTML for a menu item id. Falls through to the
     * configured default when the id has no specific entry, so the sidebar
     * never has visually empty slots. The `$extra` parameter carries slot-
     * specific styling (sizing, opacity, alignment) so the registry can
     * stay free of layout concerns.
     */
    public function renderIcon(string $itemId, string $extra = ''): string
    {
        return $this->iconRegistry->render($itemId, $extra);
    }

    public function hasIcon(string $itemId): bool
    {
        return $this->iconRegistry->hasIcon($itemId);
    }

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
