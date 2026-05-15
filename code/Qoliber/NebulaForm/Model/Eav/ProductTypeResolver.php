<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Eav;

use Magento\Catalog\Model\Product\Type;

/**
 * Thin wrapper around {@see \Magento\Catalog\Model\Product\Type} so EavForm
 * can answer "composite?" / "tracks qty?" questions without an ObjectManager
 * lookup.
 *
 * Pulled out of EavForm as part of Phase 2.
 */
class ProductTypeResolver
{
    public function __construct(
        private readonly Type $productType
    ) {
    }

    public function isComposite(string $typeCode): bool
    {
        $types = $this->productType->getTypes();

        return (bool) ($types[$typeCode]['composite'] ?? false);
    }

    public function tracksQty(string $typeCode): bool
    {
        $types = $this->productType->getTypes();

        return (bool) ($types[$typeCode]['is_qty'] ?? true);
    }
}
