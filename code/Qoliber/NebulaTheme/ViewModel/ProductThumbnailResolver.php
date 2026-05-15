<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Shared helper for ViewModels that need to resolve a product's
 * admin-grid thumbnail URL. Used by `CategoryProducts`, `RelatedProducts`.
 */
class ProductThumbnailResolver implements ArgumentInterface
{
    public function __construct(
        private readonly ImageFactory $imageHelperFactory
    ) {
    }

    public function resolve(Product $product, int $size = 60): string
    {
        try {
            return (string) $this->imageHelperFactory->create()
                ->init($product, 'product_listing_thumbnail')
                ->setImageFile((string) $product->getThumbnail())
                ->resize($size)
                ->getUrl();
        } catch (Throwable) {
            return '';
        }
    }
}
