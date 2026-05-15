<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Widget\Model\Widget\Instance;

/**
 * Reads the in-edit \Magento\Widget\Model\Widget\Instance from the
 * registry and shapes it into the Alpine-friendly initial-state dict
 * the wizard phtml renders as inline JSON.
 *
 * Magento's controller (\Magento\Widget\Controller\Adminhtml\Widget\Instance::_initWidgetInstance)
 * registers the instance under the `current_widget_instance` key for
 * both the new-widget and edit-widget flows; the new-flow instance
 * carries no id, the edit-flow instance carries the loaded fields.
 */
class WidgetInstanceData implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
    ) {
    }

    /**
     * @return array{
     *     instance_id: int|null,
     *     instance_type: string,
     *     theme_id: int|null,
     *     title: string,
     *     store_ids: list<string>,
     *     sort_order: int,
     *     parameters: array<string, mixed>,
     *     page_groups: list<array<string, mixed>>
     * }
     */
    public function getInitialState(): array
    {
        $instance = $this->registry->registry('current_widget_instance');

        if (!$instance instanceof Instance) {
            return $this->emptyShape();
        }

        return [
            'instance_id'   => ((int) $instance->getId()) ?: null,
            'instance_type' => (string) $instance->getInstanceType(),
            'theme_id'      => ((int) $instance->getThemeId()) ?: null,
            'title'         => (string) $instance->getTitle(),
            'store_ids'     => $this->normalizeStoreIds($instance->getStoreIds()),
            'sort_order'    => (int) $instance->getSortOrder(),
            'parameters'    => (array) $instance->getWidgetParameters(),
            'page_groups'   => $this->normalizePageGroups($instance->getPageGroups()),
        ];
    }

    /**
     * Converts page_groups loaded from the DB into the editable shape the
     * wizard binds to: `{page_group, block, template, for, page_id, entities,
     * layout_handle}`.
     *
     * Magento stores page-groups in three different shapes depending on lifecycle:
     *   - DB shape (\Magento\Widget\Model\ResourceModel\Widget\Instance::_afterLoad
     *     fetches raw rows from widget_instance_page):
     *     `page_group`, `layout_handle`, `block_reference`, `page_for`, `entities`,
     *     `page_template`, `page_id`.
     *   - Post-beforeSave shape (in-memory between setData() and _afterSave):
     *     `group`, `block_reference`, `for`, `template`, `entities`,
     *     `layout_handle`, `page_id`, `layout_handle_updates`.
     *   - Form-input shape (what the wizard POSTs back):
     *     `page_group`, `block`, `template`, `for`, `entities`, `layout_handle`,
     *     `page_id`.
     *
     * We alias every column name that differs from the form-input shape so a
     * row coming from any layer round-trips correctly.
     *
     * @param mixed $raw
     * @return list<array{page_group: string, block: string, template: string, for: string, page_id: string, entities: string, page_label: string, layout_handle: string}>
     */
    private function normalizePageGroups(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $rows = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $group = (string) ($entry['page_group'] ?? $entry['group'] ?? '');
            if ($group === '') {
                continue;
            }
            $rows[] = [
                'page_group'    => $group,
                'block'         => (string) ($entry['block_reference'] ?? $entry['block'] ?? 'content'),
                // DB column = `page_template`, in-memory = `template`.
                'template'      => (string) ($entry['page_template'] ?? $entry['template'] ?? ''),
                // DB column = `page_for`, in-memory = `for`.
                'for'           => (string) ($entry['page_for'] ?? $entry['for'] ?? 'all'),
                'page_id'       => (string) ($entry['page_id'] ?? '0'),
                'entities'      => (string) ($entry['entities'] ?? ''),
                'page_label'    => '',
                'layout_handle' => (string) ($entry['layout_handle'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * @return array{
     *     instance_id: null,
     *     instance_type: string,
     *     theme_id: null,
     *     title: string,
     *     store_ids: array{},
     *     sort_order: int,
     *     parameters: array{},
     *     page_groups: array{}
     * }
     */
    private function emptyShape(): array
    {
        return [
            'instance_id'   => null,
            'instance_type' => '',
            'theme_id'      => null,
            'title'         => '',
            'store_ids'     => [],
            'sort_order'    => 0,
            'parameters'    => [],
            'page_groups'   => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeStoreIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map('strval', $raw));
        }
        if (is_string($raw) && $raw !== '') {
            return array_map('trim', explode(',', $raw));
        }
        return [];
    }
}
