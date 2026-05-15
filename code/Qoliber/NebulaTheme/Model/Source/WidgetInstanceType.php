<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\Source;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Widget\Model\Widget;

/**
 * Option source for the Type filter on the widget-instance listing grid.
 *
 * Returns only widget types that actually exist in the `widget_instance`
 * table — empty options would otherwise lead the admin to filter to a
 * type with zero matches. Each row's label is resolved against Magento's
 * widget config (the same labels shown in the legacy "Insert Widget"
 * picker). Types whose config has been removed (deleted module, stale
 * data) fall back to the raw FQCN so the filter still works.
 *
 * Registered as alias `nebula.widget.instance_type` in
 * NebulaTheme/etc/adminhtml/di.xml under FilterOptionSourceRegistry.
 */
class WidgetInstanceType implements OptionSourceInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Widget $widget,
    ) {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName('widget_instance');

        $select = $connection->select()
            ->from($table, ['instance_type'])
            ->where('instance_type IS NOT NULL')
            ->where("instance_type != ''")
            ->distinct(true)
            ->order('instance_type ASC');

        $types  = $connection->fetchCol($select);
        $labels = $this->buildLabelMap();
        $rows   = [];

        foreach ($types as $type) {
            $type = (string) $type;
            $rows[] = [
                'value' => $type,
                'label' => $labels[$type] ?? $type,
            ];
        }

        return $rows;
    }

    /**
     * Build a `class-name → friendly-label` map from Magento's widget config.
     *
     * @return array<string, string>
     */
    private function buildLabelMap(): array
    {
        $map = [];

        foreach ($this->widget->getWidgetsArray() as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $type  = (string) ($entry['type'] ?? '');
            $label = (string) ($entry['name'] ?? '');
            if ($type !== '' && $label !== '') {
                $map[$type] = $label;
            }
        }

        return $map;
    }
}
