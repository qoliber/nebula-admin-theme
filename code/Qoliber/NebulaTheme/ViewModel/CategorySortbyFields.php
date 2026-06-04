<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `category_sortby_fields` snippet.
 *
 * Replaces the template's direct
 * \Magento\Framework\App\ObjectManager::getInstance() lookup of
 * \Magento\Catalog\Model\CategoryFactory with constructor injection. Exposes
 * the store-scoped "available sort by" option list and the config default
 * sort-by value that the category form's listing-sort fields render.
 */
class CategorySortbyFields implements ArgumentInterface
{
    /**
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     */
    public function __construct(
        private readonly CategoryFactory $categoryFactory
    ) {
    }

    /**
     * Resolve the available sort-by options and the config default for a store.
     *
     * Builds a single store-scoped category so both the option list and the
     * config default come from the same scope in one pass.
     *
     * @param int $storeId
     * @return array{options: array<int, array{value: string, label: string}>, configDefault: string}
     */
    public function getSortByConfig(int $storeId): array
    {
        $category = $this->categoryFactory->create();
        $category->setStoreId($storeId);

        $options = [];
        foreach ($category->getAvailableSortByOptions() as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return [
            'options' => $options,
            'configDefault' => (string) $category->getDefaultSortBy(),
        ];
    }
}
