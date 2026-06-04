<?php

declare(strict_types=1);

namespace Qoliber\NebulaUser\ViewModel;

use Magento\Authorization\Model\Acl\AclRetriever;
use Magento\Framework\Acl\AclResource\ProviderInterface as AclResourceProviderInterface;
use Magento\Framework\Acl\RootResource;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Provides the hierarchical ACL-resource tree for the role edit page's
 * "Role Resources" tab. Mirrors the stock {@see \Magento\User\Block\Role\Tab\Edit}
 * behaviour:
 *  - Discovers the resource tree via
 *    {@see \Magento\Framework\Acl\AclResource\ProviderInterface} (the same
 *    source used by `etc/acl.xml` merges).
 *  - Roots the tree at `Magento_Backend::admin` and returns its children, so
 *    the always-implied top-level node is not rendered as a checkbox.
 *  - Resolves the currently-allowed resource IDs via
 *    {@see \Magento\Authorization\Model\Acl\AclRetriever::getAllowedResourcesByRole()}
 *    (uses the `rid` request param) so the tree pre-checks them.
 *  - Exposes {@see isEverythingAllowed()} to drive the "All resources"
 *    quick-toggle — when the role's stored resource list includes the
 *    Magento\Framework\Acl\RootResource id, the tab is in "All" mode.
 *
 * The phtml template recursively renders the tree as a nested checkbox group
 * (input name = `resource[]`), with Alpine state for expand/collapse and a
 * cascade so checking a parent also checks every descendant.
 */
class RoleResourcesTree implements ArgumentInterface
{
    /** @var array<string, array<string, mixed>>|null flat id => node map (memoised) */
    private ?array $flatIndex = null;

    /** @var array<int, array<string, mixed>>|null hierarchical tree (memoised) */
    private ?array $tree = null;

    /** @var array<int, string>|null selected resource ids (memoised) */
    private ?array $selectedResources = null;

    public function __construct(
        private readonly AclResourceProviderInterface $aclResourceProvider,
        private readonly AclRetriever $aclRetriever,
        private readonly RootResource $rootResource,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Hierarchical resource tree, rooted at the children of
     * `Magento_Backend::admin`. Each node has:
     *   - id:       string ACL resource id (e.g. `Magento_Catalog::catalog`)
     *   - label:    string human title (translated)
     *   - children: list<self>
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTree(): array
    {
        if ($this->tree !== null) {
            return $this->tree;
        }

        $resources = $this->aclResourceProvider->getAclResources();

        // The top-level entry is always `Magento_Backend::admin`. Its children
        // are the real per-module resources we render; rendering the root as a
        // checkbox would let the form save the implicit "you're an admin" flag,
        // which is meaningless (only the All/Custom toggle controls that).
        $adminNode = null;
        foreach ($resources as $node) {
            if (isset($node['id']) && $node['id'] === 'Magento_Backend::admin') {
                $adminNode = $node;
                break;
            }
        }

        $children = (array) ($adminNode['children'] ?? []);

        return $this->tree = $this->normaliseNodes($children);
    }

    /**
     * @return array<int, string>
     */
    public function getSelectedResources(): array
    {
        if ($this->selectedResources !== null) {
            return $this->selectedResources;
        }

        $rid = (int) $this->request->getParam('rid', 0);
        if ($rid === 0) {
            return $this->selectedResources = [];
        }

        try {
            $allowed = $this->aclRetriever->getAllowedResourcesByRole($rid);
        } catch (\Throwable) {
            return $this->selectedResources = [];
        }

        return $this->selectedResources = array_values(array_map('strval', (array) $allowed));
    }

    public function isEverythingAllowed(): bool
    {
        return in_array($this->rootResource->getId(), $this->getSelectedResources(), true);
    }

    public function getRootResourceId(): string
    {
        return (string) $this->rootResource->getId();
    }

    /**
     * Flat id => node lookup. Used by the phtml template to pre-expand the
     * ancestor chain of every selected node (so checked items aren't hidden
     * inside collapsed parents).
     *
     * @return array<string, array{id: string, label: string, parent_id: string}>
     */
    public function getFlatIndex(): array
    {
        if ($this->flatIndex !== null) {
            return $this->flatIndex;
        }

        $flat = [];
        $walker = function (array $nodes, string $parentId = '') use (&$walker, &$flat): void {
            foreach ($nodes as $node) {
                $id = (string) $node['id'];
                $flat[$id] = [
                    'id'        => $id,
                    'label'     => (string) $node['label'],
                    'parent_id' => $parentId,
                ];
                if (!empty($node['children']) && is_array($node['children'])) {
                    $walker($node['children'], $id);
                }
            }
        };

        $walker($this->getTree());

        return $this->flatIndex = $flat;
    }

    /**
     * Build the ancestor-id list for each selected resource so the template
     * can auto-expand the path that leads to a checked box.
     *
     * @return array<string, bool>
     */
    public function getExpandedAncestors(): array
    {
        $flat = $this->getFlatIndex();
        $expanded = [];

        foreach ($this->getSelectedResources() as $id) {
            $current = (string) $id;
            while (isset($flat[$current]) && $flat[$current]['parent_id'] !== '') {
                $parent = $flat[$current]['parent_id'];
                $expanded[$parent] = true;
                $current = $parent;
            }
        }

        return $expanded;
    }

    /**
     * Normalise the AclResource provider output into the lighter shape the
     * template uses. Each node's `title` (vendor) → `label` (ours), nested
     * `children` recursively normalised, missing keys defaulted.
     *
     * @param array<int|string, array<string, mixed>> $nodes
     * @return array<int, array{id: string, label: string, children: array}>
     */
    private function normaliseNodes(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (!isset($node['id']) || $node['id'] === '') {
                continue;
            }
            $out[] = [
                'id'       => (string) $node['id'],
                'label'    => (string) (
                    $node['title'] ?? $node['label'] ?? $node['id']
                ),
                'children' => isset($node['children']) && is_array($node['children'])
                    ? $this->normaliseNodes($node['children'])
                    : [],
            ];
        }

        return $out;
    }
}
