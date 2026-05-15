<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `category_products` snippet. Walks the category's
 * assigned products and builds a structured payload (id, sku, name,
 * price, thumbnail, position).
 */
class CategoryProducts implements ArgumentInterface
{
    public function __construct(
        private readonly ProductThumbnailResolver $thumbnailResolver
    ) {
    }

    /**
     * @return list<array{id: int, sku: string, name: string, price: float|null, thumbnail: string, position: int}>
     */
    public function getAssignedProducts(?Category $category): array
    {
        if ($category === null || !$category->getId()) {
            return [];
        }

        $collection = $category->getProductCollection();
        $collection->addAttributeToSelect(['name', 'sku', 'price', 'thumbnail']);
        $collection->setPageSize(100);

        $products = [];
        foreach ($collection as $product) {
            $products[] = [
                'id' => (int) $product->getId(),
                'sku' => (string) $product->getSku(),
                'name' => (string) $product->getName(),
                'price' => $product->getPrice() ? (float) $product->getPrice() : null,
                'thumbnail' => $this->thumbnailResolver->resolve($product),
                'position' => (int) $product->getData('cat_index_position'),
            ];
        }

        return $products;
    }
}
