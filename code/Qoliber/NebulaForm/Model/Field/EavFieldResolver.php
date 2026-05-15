<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Field;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Qoliber\NebulaForm\Model\Eav\ProductTypeResolver;

/**
 * Answers two orthogonal questions about an EAV attribute in the context of
 * a Nebula form:
 *
 *   1. Is the attribute applicable to the current entity type?
 *      (respects `apply_to` for products; falls back to "yes" otherwise.)
 *   2. What override block applies to it from the form definition?
 *
 * This is the knowledge that used to live as a fan-out of private methods
 * inside EavForm.
 */
class EavFieldResolver
{
    /**
     * Attribute codes that ARE rendered by Nebula but via a dedicated
     * snippet rather than the generic field renderer. We never want to
     * also auto-render them as plain inputs — that would duplicate the
     * field (e.g., `tier_price` would show up as both the rich
     * tier-prices editor AND a raw text input under "Additional
     * Attributes").
     */
    private const HANDLED_BY_SNIPPET = [
        'media_gallery',
        'image',
        'small_image',
        'thumbnail',
        'swatch_image',
        'image_label',
        'small_image_label',
        'thumbnail_label',
        'swatch_image_label',
        'tier_price',
        'category_ids',
        'quantity_and_stock_status',
        'qty',
    ];

    public function __construct(
        private readonly ProductTypeResolver $productTypeResolver
    ) {
    }

    /**
     * @param array<string, mixed> $definition Full form definition.
     * @return array<string, mixed> Empty array when no override exists.
     */
    public function getFieldOverrides(array $definition, string $attributeCode): array
    {
        return $definition['overrides']['fields'][$attributeCode] ?? [];
    }

    /**
     * True when the attribute applies to the currently-edited entity. Honors:
     *
     *   - the EAV `is_visible` flag — system attributes (`url_path`,
     *     `required_options`, `has_options`, `created_at`, `updated_at`,
     *     etc.) carry `is_visible=0` and should not surface as editable
     *     fields in the admin
     *   - a code-allowlist of attributes that ARE editable but are owned
     *     by a dedicated Nebula snippet (media gallery, tier prices,
     *     category tree, stock fields) — those would render twice if we
     *     also emitted them as plain inputs
     *   - the `apply_to` metadata for products — respects per-type
     *     allowlists, falls back to "yes" for non-product entities or
     *     attributes with no `apply_to` set
     *
     * @param array<string, mixed> $entityData
     */
    public function isApplicable(AbstractAttribute $attribute, array $entityData): bool
    {
        // System / non-admin-visible attributes never render. Mirrors the
        // filter Magento's vendor UI Component runs via
        // \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Eav.
        if ($attribute->hasData('is_visible') && !$attribute->getData('is_visible')) {
            return false;
        }

        $code = (string) $attribute->getAttributeCode();
        if (in_array($code, self::HANDLED_BY_SNIPPET, true)) {
            return false;
        }

        $applyTo = $attribute->getData('apply_to');

        if (empty($applyTo)) {
            return true;
        }

        $productType = (string) ($entityData['type_id'] ?? 'simple');
        $applicableTypes = is_string($applyTo) ? explode(',', $applyTo) : (array) $applyTo;

        return in_array($productType, $applicableTypes, true);
    }

    public function isComposite(string $typeCode): bool
    {
        return $this->productTypeResolver->isComposite($typeCode);
    }

    public function tracksQty(string $typeCode): bool
    {
        return $this->productTypeResolver->tracksQty($typeCode);
    }
}
