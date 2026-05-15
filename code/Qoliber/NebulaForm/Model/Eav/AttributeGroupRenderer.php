<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Eav;

use Qoliber\NebulaForm\Block\EavForm;
use Qoliber\NebulaForm\Model\Field\EavFieldResolver;

/**
 * Computes the list of attribute groups (and their attributes) that were NOT
 * explicitly laid out in the form JSON and are therefore eligible for
 * auto-rendering at the tail of the form.
 *
 * Extracted from {@see EavForm::getRemainingAttributeGroups()}.
 */
class AttributeGroupRenderer
{
    /** @var array<int, string> */
    private const SKIP_FRONTEND_INPUTS = ['gallery', 'media_image'];

    /** @var array<int, string> */
    private const SKIP_ATTRIBUTE_CODES = ['category_ids', 'quantity_and_stock_status'];

    public function __construct(
        private readonly AttributeGroupLoader $groupLoader,
        private readonly EavFieldResolver $fieldResolver
    ) {
    }

    /**
     * @param array<string, mixed>      $definition   Full form definition.
     * @param array<string, mixed>      $entityData   Current entity data (for applicability checks).
     * @param array<int, string>        $explicitCodes  Attribute codes already rendered by the layout.
     * @param array<int, string>        $explicitGroups Group codes explicitly rendered.
     *
     * @return array<string, array{label: string, attributes: \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[]}>
     */
    public function getRemainingGroups(
        string $entityTypeCode,
        array $definition,
        array $entityData,
        array $explicitCodes,
        array $explicitGroups
    ): array {
        $excludeGroups = $definition['excludeGroups'] ?? [];
        $attributeSetId = isset($entityData['attribute_set_id'])
            ? (int) $entityData['attribute_set_id']
            : null;
        $result = [];

        foreach ($this->groupLoader->getAttributeGroups($entityTypeCode, $attributeSetId) as $group) {
            $groupCode = $group->getAttributeGroupCode();

            if (in_array($groupCode, $explicitGroups, true)) {
                continue;
            }

            if (in_array($groupCode, $excludeGroups, true)) {
                continue;
            }

            $filtered = $this->filterAttributes(
                $this->groupLoader->getGroupAttributes($entityTypeCode, $groupCode, $attributeSetId),
                $explicitCodes,
                $entityData
            );

            if ($filtered !== []) {
                $result[$groupCode] = [
                    'label' => $group->getAttributeGroupName(),
                    'attributes' => $filtered,
                ];
            }
        }

        return $result;
    }

    /**
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[] $attributes
     * @param array<int, string> $explicitCodes
     * @param array<string, mixed> $entityData
     * @return \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[]
     */
    private function filterAttributes(array $attributes, array $explicitCodes, array $entityData): array
    {
        $filtered = [];

        foreach ($attributes as $attribute) {
            $code = $attribute->getAttributeCode();

            if (in_array($code, $explicitCodes, true)) {
                continue;
            }
            if (in_array($code, self::SKIP_ATTRIBUTE_CODES, true)) {
                continue;
            }
            if (in_array($attribute->getFrontendInput(), self::SKIP_FRONTEND_INPUTS, true)) {
                continue;
            }
            if (!$attribute->getFrontendInput()) {
                continue;
            }
            if (method_exists($attribute, 'getIsVisible') && !$attribute->getIsVisible()) {
                continue;
            }
            if (!$this->fieldResolver->isApplicable($attribute, $entityData)) {
                continue;
            }

            $filtered[] = $attribute;
        }

        return $filtered;
    }
}
