<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Model\Form;

use Magento\Framework\Escaper;
use Magento\Framework\View\LayoutInterface;
use Qoliber\NebulaForm\Block\EavForm;
use Qoliber\NebulaForm\Model\Field\EavFieldRenderer;

/**
 * Walks an EAV form layout tree and renders it to HTML.
 *
 * Extracts what used to be a 150-line closure at the top of eav/form.phtml.
 * Node rendering delegates to per-type partials under
 * `NebulaForm/view/adminhtml/templates/eav/nodes/{row,column,section,tabs}.phtml`.
 */
class LayoutTreeRenderer
{
    public function __construct(
        private readonly EavFieldRenderer $fieldRenderer,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     */
    public function render(EavForm $block, array $tree, LayoutInterface $layout): string
    {
        $html = '';

        foreach ($tree as $node) {
            if (!is_array($node)) {
                continue;
            }

            try {
                $html .= $this->renderNode($block, $node, $layout);
            } catch (\Throwable $e) {
                $html .= $this->renderError($e);
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $node
     */
    public function renderNode(EavForm $block, array $node, LayoutInterface $layout): string
    {
        if (!$this->checkCondition($block, $node)) {
            return '';
        }

        $type = $node['type'] ?? '';

        return match ($type) {
            'row' => $this->renderRow($block, $node, $layout),
            'column' => $this->renderColumn($block, $node, $layout),
            'section' => $this->renderSection($block, $node, $layout),
            'tabs' => $this->renderTabs($block, $node, $layout),
            default => $this->renderChildren($block, $node['children'] ?? [], $layout),
        };
    }

    private function renderRow(EavForm $block, array $node, LayoutInterface $layout): string
    {
        return '<div class="flex flex-col lg:flex-row w-full gap-6 mb-6">'
            . $this->renderChildren($block, $node['children'] ?? [], $layout)
            . '</div>';
    }

    private function renderColumn(EavForm $block, array $node, LayoutInterface $layout): string
    {
        $widthClass = match ($node['width'] ?? '') {
            '1/2' => 'w-full lg:w-1/2',
            '1/3' => 'w-full lg:w-1/3',
            '2/3' => 'w-full lg:w-2/3',
            '1/4' => 'w-full lg:w-1/4',
            '3/4' => 'w-full lg:w-3/4',
            default => 'flex-1 min-w-0',
        };

        return '<div class="' . $widthClass . '">'
            . $this->renderChildren($block, $node['children'] ?? [], $layout)
            . '</div>';
    }

    private function renderSection(EavForm $block, array $node, LayoutInterface $layout): string
    {
        $label = (string) ($node['label'] ?? '');
        $isRendererOnly = !empty($node['renderer']) && $label === '';

        $body = '';

        if (!empty($node['renderer'])) {
            $body .= $block->renderSection($node);
        } elseif (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $childType = $child['type'] ?? '';

                if ($childType === 'fields' && !empty($child['fields'])) {
                    $body .= '<div class="divide-y divide-gray-100">';
                    foreach ($child['fields'] as $attrCode) {
                        $body .= $this->fieldRenderer->render($block, (string) $attrCode, $layout);
                    }
                    $body .= '</div>';
                } elseif ($childType === 'snippet' && !empty($child['renderer'])) {
                    $body .= $block->renderSection($child);
                } else {
                    $body .= $this->renderNode($block, $child, $layout);
                }
            }
        } elseif (!empty($node['fields'])) {
            $body .= '<div class="divide-y divide-gray-100">';
            foreach ($node['fields'] as $attrCode) {
                $body .= $this->fieldRenderer->render($block, (string) $attrCode, $layout);
            }
            $body .= '</div>';
        } elseif (!empty($node['groups'])) {
            $body .= '<div class="divide-y divide-gray-100">';
            foreach ($node['groups'] as $groupCode) {
                foreach ($block->getGroupAttributes((string) $groupCode) as $attribute) {
                    $body .= $this->fieldRenderer->render($block, (string) $attribute->getAttributeCode(), $layout);
                }
            }
            $body .= '</div>';
        }

        if ($isRendererOnly) {
            return $body;
        }

        $header = '';
        if ($label !== '') {
            $header = '<div class="border-b border-gray-200 px-6 py-4">'
                . '<h2 class="text-base font-semibold text-gray-900">'
                . $this->escaper->escapeHtml($block->translate($label))
                . '</h2></div>';
        }

        return '<div class="rounded-lg border border-gray-200 bg-white shadow-sm mb-6">'
            . $header
            . '<div class="px-6 py-4">' . $body . '</div>'
            . '</div>';
    }

    private function renderTabs(EavForm $block, array $node, LayoutInterface $layout): string
    {
        $children = $node['children'] ?? [];
        $nav = '';
        $panels = '';

        foreach ($children as $i => $tab) {
            if (!is_array($tab)) {
                continue;
            }
            $tabLabel = (string) ($tab['label'] ?? ('Tab ' . ($i + 1)));
            $nav .= '<button type="button" @click="activeTab = ' . (int) $i . '"'
                . ' :class="activeTab === ' . (int) $i
                . ' ? \'border-indigo-500 text-indigo-600\' : \'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700\'"'
                . ' class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition">'
                . $this->escaper->escapeHtml($block->translate($tabLabel))
                . '</button>';

            $panelBody = '';
            if (!empty($tab['renderer'])) {
                $panelBody = $block->renderSection($tab);
            } elseif (!empty($tab['fields'])) {
                $panelBody = '<div class="rounded-lg border border-gray-200 bg-white shadow-sm">'
                    . '<div class="px-6 py-4 divide-y divide-gray-100">';
                foreach ($tab['fields'] as $attrCode) {
                    $panelBody .= $this->fieldRenderer->render($block, (string) $attrCode, $layout);
                }
                $panelBody .= '</div></div>';
            } elseif (!empty($tab['children'])) {
                $panelBody = $this->renderChildren($block, $tab['children'], $layout);
            } elseif (!empty($tab['groups'])) {
                $panelBody = '<div class="rounded-lg border border-gray-200 bg-white shadow-sm">'
                    . '<div class="px-6 py-4 divide-y divide-gray-100">';
                foreach ($tab['groups'] as $groupCode) {
                    foreach ($block->getGroupAttributes((string) $groupCode) as $attribute) {
                        $panelBody .= $this->fieldRenderer->render($block, (string) $attribute->getAttributeCode(), $layout);
                    }
                }
                $panelBody .= '</div></div>';
            }

            $panels .= '<div x-show="activeTab === ' . (int) $i . '">' . $panelBody . '</div>';
        }

        return '<div x-data="{ activeTab: 0 }" class="mb-6">'
            . '<div class="border-b border-gray-200"><nav class="flex gap-1 overflow-x-auto" aria-label="Section Tabs">' . $nav . '</nav></div>'
            . '<div class="mt-4">' . $panels . '</div>'
            . '</div>';
    }

    private function renderChildren(EavForm $block, array $children, LayoutInterface $layout): string
    {
        $html = '';

        foreach ($children as $child) {
            if (is_array($child)) {
                $html .= $this->renderNode($block, $child, $layout);
            }
        }

        return $html;
    }

    /**
     * Condition format: {"field_name": "value"} or {"field_name": ["val1", "val2"]}.
     */
    private function checkCondition(EavForm $block, array $node): bool
    {
        if (empty($node['condition'])) {
            return true;
        }

        foreach ($node['condition'] as $field => $expected) {
            $actual = $block->getFieldValue((string) $field);

            if (is_array($expected)) {
                if (!in_array((string) $actual, array_map('strval', $expected), true)) {
                    return false;
                }
            } else {
                if ((string) $actual !== (string) $expected) {
                    return false;
                }
            }
        }

        return true;
    }

    private function renderError(\Throwable $e): string
    {
        return '<div class="rounded-lg border border-red-300 bg-red-50 p-4 mb-6 text-sm text-red-700">'
            . '<strong>Render error:</strong> '
            . $this->escaper->escapeHtml($e->getMessage())
            . '<br><code>'
            . $this->escaper->escapeHtml($e->getFile() . ':' . $e->getLine())
            . '</code></div>';
    }
}
