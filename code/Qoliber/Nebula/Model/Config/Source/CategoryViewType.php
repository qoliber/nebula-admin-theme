<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for Nebula Category View Type.
 */
class CategoryViewType implements OptionSourceInterface
{
    public const STANDARD = 'standard';
    public const REFRESHED = 'refreshed';
    public const UNIFIED = 'unified';
    public const TABBED_SIDEBAR = 'tabbed_sidebar';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::STANDARD, 'label' => __('Standard (Sidebar + Form)')],
            ['value' => self::REFRESHED, 'label' => __('Refreshed (Drawer Tree + Full Tabs)')],
            ['value' => self::UNIFIED, 'label' => __('Unified (Drawer Tree + Top Sections + Tabs)')],
            ['value' => self::TABBED_SIDEBAR, 'label' => __('Tabbed Sidebar (Sidebar on Desktop, Drawer on Mobile)')],
        ];
    }
}
