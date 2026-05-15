<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * ViewModel for the `media_gallery` snippet. Builds the payload that
 * the Alpine media-gallery widget consumes on init — thumbnails,
 * positions, roles.
 */
class MediaGallery implements ArgumentInterface
{
    public function __construct(
        private readonly MediaConfig $mediaConfig,
        private readonly ImageFactory $imageHelperFactory
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $existingImages
     * @return list<array<string, mixed>>
     */
    public function buildImagesData(?Product $entity, array $existingImages): array
    {
        $imagesData = [];
        foreach ($existingImages as $img) {
            $filePath = (string) ($img['file'] ?? '');
            $thumbUrl = $this->resolveThumbUrl($entity, $filePath);
            $imagesData[] = [
                'value_id' => $img['value_id'] ?? null,
                'file' => $filePath,
                'url' => $thumbUrl,
                'label' => (string) ($img['label'] ?? ''),
                'position' => (int) ($img['position'] ?? 0),
                'disabled' => (bool) ($img['disabled'] ?? false),
                'removed' => false,
                'media_type' => (string) ($img['media_type'] ?? 'image'),
                'new' => false,
            ];
        }

        usort(
            $imagesData,
            static fn (array $a, array $b): int => $a['position'] <=> $b['position']
        );

        return $imagesData;
    }

    private function resolveThumbUrl(?Product $entity, string $filePath): string
    {
        if ($entity === null || $filePath === '') {
            return $this->mediaConfig->getMediaUrl($filePath);
        }

        try {
            return (string) $this->imageHelperFactory->create()
                ->init($entity, 'product_listing_thumbnail')
                ->setImageFile($filePath)
                ->resize(150)
                ->getUrl();
        } catch (Throwable) {
            return $this->mediaConfig->getMediaUrl($filePath);
        }
    }
}
