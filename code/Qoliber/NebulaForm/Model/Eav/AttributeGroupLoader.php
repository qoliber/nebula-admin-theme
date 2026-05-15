<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Eav;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Group as AttributeGroup;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as EavAttributeCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;

/**
 * Loads EAV attribute groups + group attributes.
 *
 * Extracted from {@see \Qoliber\NebulaForm\Block\EavForm} so the block stops
 * constructing EAV collections via ObjectManager.
 */
class AttributeGroupLoader
{
    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly GroupCollectionFactory $groupCollectionFactory,
        private readonly EavAttributeCollectionFactory $eavAttributeCollectionFactory
    ) {
    }

    /**
     * @return \Magento\Eav\Model\Entity\Attribute\Group[]
     */
    public function getAttributeGroups(string $entityTypeCode, ?int $attributeSetId = null): array
    {
        $entityType = $this->eavConfig->getEntityType($entityTypeCode);
        $setId = $attributeSetId ?? (int) $entityType->getDefaultAttributeSetId();

        $groupCollection = $this->groupCollectionFactory->create();
        $groupCollection->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->load();

        /** @var \Magento\Eav\Model\Entity\Attribute\Group[] $items */
        $items = $groupCollection->getItems();
        return $items;
    }

    /**
     * @return \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[]
     */
    public function getGroupAttributes(string $entityTypeCode, string $groupCode, ?int $attributeSetId = null): array
    {
        $entityType = $this->eavConfig->getEntityType($entityTypeCode);
        $setId = $attributeSetId ?? (int) $entityType->getDefaultAttributeSetId();
        $groupId = $this->resolveGroupId($entityTypeCode, $groupCode, $setId);

        if ($groupId === null) {
            return [];
        }

        $attributeCollection = $this->eavAttributeCollectionFactory->create();
        $attributeCollection->setAttributeSetFilter($setId)
            ->setAttributeGroupFilter($groupId)
            ->setOrder('sort_order', 'ASC')
            ->load();

        /** @var \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[] $items */
        $items = $attributeCollection->getItems();
        return $items;
    }

    public function getAttribute(string $entityTypeCode, string $attributeCode): ?AbstractAttribute
    {
        try {
            return $this->eavConfig->getAttribute($entityTypeCode, $attributeCode);
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveGroupId(string $entityTypeCode, string $groupCode, int $attributeSetId): ?int
    {
        foreach ($this->getAttributeGroups($entityTypeCode, $attributeSetId) as $group) {
            if ($group instanceof AttributeGroup && $group->getAttributeGroupCode() === $groupCode) {
                return (int) $group->getAttributeGroupId();
            }
        }

        return null;
    }
}
