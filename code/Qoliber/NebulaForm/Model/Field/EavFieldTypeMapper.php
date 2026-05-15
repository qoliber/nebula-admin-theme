<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Field;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Maps an EAV attribute (plus any form-definition overrides) to the Nebula
 * field type name used for rendering.
 *
 * Pulled out of {@see \Qoliber\NebulaForm\Block\EavForm::getFieldType()} so the
 * mapping table isn't re-declared in the block.
 */
class EavFieldTypeMapper
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function map(AbstractAttribute $attribute, array $overrides = []): string
    {
        if (!empty($overrides['type'])) {
            return (string) $overrides['type'];
        }

        return match ($attribute->getFrontendInput()) {
            'text' => 'text',
            'textarea' => 'textarea',
            'select' => 'select',
            'multiselect' => 'multiselect',
            'boolean' => 'toggle',
            'date', 'datetime' => 'date',
            'price', 'weight' => 'number',
            'media_image' => 'image',
            'gallery' => 'skip',
            'hidden' => 'hidden',
            default => 'text',
        };
    }

    /**
     * Normalised source-options for a single attribute; returns empty when the
     * attribute has no source or its source raised.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getOptions(AbstractAttribute $attribute): array
    {
        try {
            $source = $attribute->getSource();
        } catch (\Throwable) {
            // Misconfigured source_model (e.g. pointing at the abstract
            // `Magento\Eav\Model\Entity\Attribute\Source\Config` base class
            // instead of a virtualType) will throw a TypeError at DI time.
            // One bad attribute must not kill the whole form render.
            return [];
        }

        if ($source === null) {
            return [];
        }

        try {
            return $source->getAllOptions();
        } catch (\Throwable) {
            return [];
        }
    }
}
