<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the `bundle_options` snippet. Loads the options +
 * selections for a bundle product and normalises them to a serialisable
 * payload consumed by the Alpine wizard.
 */
class BundleOptions implements ArgumentInterface
{
    public function __construct(
        private readonly ProductThumbnailResolver $thumbnailResolver
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getExistingOptions(Product $entity): array
    {
        $typeInstance = $entity->getTypeInstance();
        $optionsCollection = $typeInstance->getOptionsCollection($entity);
        $selectionsCollection = $typeInstance->getSelectionsCollection(
            $typeInstance->getOptionsIds($entity),
            $entity
        );

        $existing = [];
        foreach ($optionsCollection as $option) {
            $optData = [
                'option_id' => $option->getOptionId(),
                'title' => $option->getTitle(),
                'type' => $option->getType(),
                'required' => (bool) $option->getRequired(),
                'position' => (int) $option->getPosition(),
                'selections' => [],
            ];

            foreach ($selectionsCollection as $selection) {
                if ($selection->getOptionId() != $option->getOptionId()) {
                    continue;
                }
                $optData['selections'][] = [
                    'selection_id' => $selection->getSelectionId(),
                    'product_id' => (int) $selection->getProductId(),
                    'name' => $selection->getName(),
                    'sku' => $selection->getSku(),
                    'price' => (float) $selection->getSelectionPriceValue(),
                    'price_type' => (int) $selection->getSelectionPriceType(),
                    'qty' => (float) $selection->getSelectionQty(),
                    'is_default' => (bool) $selection->getIsDefault(),
                    'can_change_qty' => (bool) $selection->getSelectionCanChangeQty(),
                    'position' => (int) $selection->getPosition(),
                    'thumbnail' => $this->thumbnailResolver->resolve($selection, 40),
                ];
            }

            $existing[] = $optData;
        }

        return $existing;
    }
}
