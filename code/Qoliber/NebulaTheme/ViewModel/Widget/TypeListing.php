<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\Widget;

use Magento\Framework\View\Design\Theme\Label\ListInterface as ThemeLabelList;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Widget\Model\Widget;

/**
 * Read-only catalogs for the widget-wizard's step-1 picker: list of
 * installed widget types, list of themes, and the FQCN→code map the
 * Save controller needs.
 *
 * Registered via NebulaTheme/etc/adminhtml/di.xml — no explicit type
 * rule needed, ObjectManager constructs from declared constructor args.
 */
class TypeListing implements ArgumentInterface
{
    public function __construct(
        private readonly Widget $widget,
        private readonly ThemeLabelList $themeLabels,
    ) {
    }

    /** @return list<array{value: string, label: string}> */
    public function getWidgetTypes(): array
    {
        $rows = [];
        foreach ($this->widget->getWidgetsArray() as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $type  = (string) ($entry['type'] ?? '');
            $label = (string) ($entry['name'] ?? '');
            if ($type !== '' && $label !== '') {
                $rows[] = ['value' => $type, 'label' => $label];
            }
        }
        return $rows;
    }

    /**
     * Mirrors what Magento's legacy widget-instance Settings tab does:
     * uses \Magento\Framework\View\Design\Theme\Label\ListInterface which
     * returns themes filtered to those eligible for widget binding
     * (frontend-area, visible, non-virtual). Concrete ThemeList::getItems
     * returns nothing in this context — wrong API for the widget picker.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getThemes(): array
    {
        $rows = [];
        foreach ($this->themeLabels->getLabels() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = (string) ($row['value'] ?? '');
            $label = (string) ($row['label'] ?? '');
            if ($value !== '' && $label !== '') {
                $rows[] = ['value' => $value, 'label' => $label];
            }
        }
        return $rows;
    }

    /**
     * Map from widget-class FQCN → widget code (the short id like
     * `cms_static_block`). Magento's Save controller looks up the type
     * from the POSTed `code` parameter via getWidgetReference — so the
     * wizard needs the code to round-trip a save on the new-widget flow.
     *
     * @return array<string, string>
     */
    public function getTypeCodeMap(): array
    {
        $map = [];
        foreach ($this->widget->getWidgetsArray() as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $type = (string) ($entry['type'] ?? '');
            $code = (string) ($entry['code'] ?? '');
            if ($type !== '' && $code !== '') {
                $map[$type] = $code;
            }
        }
        return $map;
    }
}
