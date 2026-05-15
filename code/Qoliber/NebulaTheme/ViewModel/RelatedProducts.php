<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * ViewModel for the `related_products` snippet (related/upsell/crosssell).
 * Normalises the three link-type collections behind one lookup.
 */
class RelatedProducts implements ArgumentInterface
{
    /** @var array<string, string> link type alias → collection method on Product */
    private const COLLECTION_METHOD_MAP = [
        'related' => 'getRelatedProductCollection',
        'upsell' => 'getUpSellProductCollection',
        'crosssell' => 'getCrossSellProductCollection',
    ];

    public function __construct(
        private readonly ProductThumbnailResolver $thumbnailResolver
    ) {
    }

    /**
     * @return list<array{id: int, sku: string, name: string, price: float|null, thumbnail: string}>
     */
    public function getLinkedProducts(?Product $entity, string $linkType): array
    {
        if ($entity === null) {
            return [];
        }

        $method = self::COLLECTION_METHOD_MAP[$linkType] ?? self::COLLECTION_METHOD_MAP['related'];
        if (!method_exists($entity, $method)) {
            return [];
        }

        try {
            $collection = $entity->$method();
            $collection->addAttributeToSelect(['name', 'sku', 'price', 'thumbnail', 'small_image']);
        } catch (Throwable) {
            return [];
        }

        $products = [];
        foreach ($collection as $product) {
            $products[] = [
                'id' => (int) $product->getId(),
                'sku' => (string) $product->getSku(),
                'name' => (string) $product->getName(),
                'price' => $product->getPrice() ? (float) $product->getPrice() : null,
                'thumbnail' => $this->thumbnailResolver->resolve($product),
            ];
        }

        return $products;
    }

    public function resolveLinkType(string $sectionLabel): string
    {
        $label = strtolower($sectionLabel);
        if (str_contains($label, 'upsell')) {
            return 'upsell';
        }
        if (str_contains($label, 'cross')) {
            return 'crosssell';
        }

        return 'related';
    }
}
