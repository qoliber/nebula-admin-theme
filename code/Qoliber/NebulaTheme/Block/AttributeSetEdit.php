<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Block;

use Magento\Catalog\Model\Entity\Product\Attribute\Group\AttributeMapperInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\GroupFactory;
use Magento\Eav\Model\Entity\TypeFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class AttributeSetEdit extends Template
{
    public function __construct(
        Context $context,
        private readonly FormKey $formKey,
        private readonly Registry $coreRegistry,
        private readonly CollectionFactory $collectionFactory,
        private readonly GroupFactory $groupFactory,
        private readonly TypeFactory $typeFactory,
        private readonly EncoderInterface $jsonEncoder,
        private readonly AttributeMapperInterface $attributeMapper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->setTemplate('Qoliber_NebulaTheme::attribute-set/edit.phtml');
    }

    public function getAttributeSetId(): ?int
    {
        $set = $this->coreRegistry->registry('current_attribute_set');
        return $set ? (int) $set->getId() : null;
    }

    public function getAttributeSetName(): string
    {
        $set = $this->coreRegistry->registry('current_attribute_set');
        return $set ? (string) $set->getAttributeSetName() : '';
    }

    public function isDefaultSet(): bool
    {
        $entityTypeId = $this->coreRegistry->registry('entityType');
        if (!$entityTypeId) {
            return false;
        }
        $defaultSetId = $this->typeFactory->create()->load($entityTypeId)->getDefaultAttributeSetId();
        return $this->getAttributeSetId() == $defaultSetId;
    }

    public function getGroupsJson(): string
    {
        $setId = $this->getAttributeSetId();
        if (!$setId) {
            return '[]';
        }

        $groups = $this->groupFactory->create()
            ->getResourceCollection()
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->load();

        $items = [];
        foreach ($groups as $group) {
            $attributes = $this->collectionFactory->create()
                ->setAttributeGroupFilter($group->getId())
                ->addVisibleFilter()
                ->load();

            $attrs = [];
            foreach ($attributes as $attr) {
                $mapped = $this->attributeMapper->map($attr);
                $attrs[] = [
                    'attribute_id' => (int) $attr->getAttributeId(),
                    'code' => $attr->getAttributeCode(),
                    'label' => $attr->getFrontendLabel() ?: $attr->getAttributeCode(),
                    'entity_id' => (int) $attr->getEntityAttributeId(),
                    'is_user_defined' => (bool) $attr->getIsUserDefined(),
                    'is_unassignable' => $mapped['is_unassignable'] ?? true,
                ];
            }

            $items[] = [
                'id' => (int) $group->getAttributeGroupId(),
                'name' => $group->getAttributeGroupName(),
                'sort_order' => (int) $group->getSortOrder(),
                'attributes' => $attrs,
            ];
        }

        return $this->jsonEncoder->encode($items);
    }

    public function getUnassignedAttributesJson(): string
    {
        $setId = $this->getAttributeSetId();
        if (!$setId) {
            return '[]';
        }

        $assigned = $this->collectionFactory->create()
            ->setAttributeSetFilter($setId)
            ->load();

        $assignedIds = ['0'];
        foreach ($assigned as $attr) {
            $assignedIds[] = $attr->getAttributeId();
        }

        $unassigned = $this->collectionFactory->create()
            ->setAttributesExcludeFilter($assignedIds)
            ->addVisibleFilter()
            ->load();

        $items = [];
        foreach ($unassigned as $attr) {
            $items[] = [
                'attribute_id' => (int) $attr->getAttributeId(),
                'code' => $attr->getAttributeCode(),
                'label' => $attr->getFrontendLabel() ?: $attr->getAttributeCode(),
                'is_user_defined' => (bool) $attr->getIsUserDefined(),
                'entity_id' => 0,
            ];
        }

        return $this->jsonEncoder->encode($items);
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('catalog/product_set/save', ['id' => $this->getAttributeSetId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('catalog/product_set/');
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('catalog/product_set/delete', ['id' => $this->getAttributeSetId()]);
    }
}
