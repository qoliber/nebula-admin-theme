<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `category_tree` + `category_navigation_tree` snippets.
 *
 * Walks the category table once and returns either a flat list of
 * options or a nested tree, depending on what the caller needs.
 */
class CategoryTree implements ArgumentInterface
{
    /** @var array<string, array{id: string, name: string, parent_id: string, level: int, is_active: bool, children: array<int, mixed>}>|null */
    private ?array $items = null;

    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * Flat map keyed by category id.
     *
     * @return array<string, array{id: string, name: string, parent_id: string, level: int, is_active: bool, children: array<int, mixed>}>
     */
    public function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active')
            ->addFieldToFilter('level', ['gt' => 0])
            ->setOrder('position', 'ASC');

        $items = [];
        foreach ($collection as $category) {
            $items[(string) $category->getId()] = [
                'id' => (string) $category->getId(),
                'name' => (string) $category->getName(),
                'parent_id' => (string) $category->getParentId(),
                'level' => (int) $category->getLevel(),
                'is_active' => (bool) $category->getIsActive(),
                'children' => [],
            ];
        }

        return $this->items = $items;
    }

    /**
     * Nested tree built from {@see getItems()}.
     *
     * @return list<array<string, mixed>>
     */
    public function getTree(): array
    {
        $items = $this->getItems();
        $tree = [];
        foreach ($items as $id => &$item) {
            if (isset($items[$item['parent_id']])) {
                $items[$item['parent_id']]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);

        return $tree;
    }
}
