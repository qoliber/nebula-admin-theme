<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel\Widget;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Model\PageLayout\Config\BuilderInterface as PageLayoutConfigBuilder;
use Magento\Widget\Model\Widget\Instance as WidgetInstance;

/**
 * Page-group catalog for the Layout Updates section: the static
 * Categories + Generic Pages groups (matching Magento core) plus the
 * dynamic per-product-type rows enumerated from \Magento\Catalog\Model\Product\Type.
 *
 * Two outputs:
 *   - getPageGroupOptions(): the Display On dropdown rows.
 *   - getLayoutHandleMap():  page_group → frontend layout handle. The
 *     wizard's Container AJAX needs the handle to walk the right layout.
 *
 * "Specified Page" and "Page Layouts" are deliberately omitted from B1 —
 * they require entity-selection choosers that ship in B2.
 */
class PageGroupCatalog implements ArgumentInterface
{
    public function __construct(
        private readonly ProductType $productType,
        private readonly PageLayoutConfigBuilder $pageLayoutConfigBuilder,
    ) {
    }

    /**
     * @return list<array{value: string, label: string, group: string}>
     */
    public function getPageGroupOptions(): array
    {
        $rows = [];
        $rows[] = ['value' => 'all_pages',            'label' => 'All Pages',             'group' => 'Generic Pages'];
        $rows[] = ['value' => 'pages',                'label' => 'Specified Page',        'group' => 'Generic Pages'];
        $rows[] = ['value' => 'page_layouts',         'label' => 'Page Layouts',          'group' => 'Generic Pages'];
        $rows[] = ['value' => 'anchor_categories',    'label' => 'Anchor Categories',     'group' => 'Categories'];
        $rows[] = ['value' => 'notanchor_categories', 'label' => 'Non-Anchor Categories', 'group' => 'Categories'];
        $rows[] = ['value' => 'all_products',         'label' => 'All Product Types',     'group' => 'Products'];
        foreach ($this->productType->getTypes() as $typeId => $type) {
            $rows[] = [
                'value' => $typeId . '_products',
                'label' => (string) ($type['label'] ?? $typeId),
                'group' => 'Products',
            ];
        }
        return $rows;
    }

    /**
     * Page-layout handles for the Specified Page Layout group's
     * layout_handle dropdown. Mirrors what the legacy widget admin
     * shows there, sourced dynamically from the page-layouts config.
     *
     * \Magento\Framework\View\PageLayout\Config::getPageLayouts() returns
     * a `string[][]` map — `['1column' => ['label' => '1 column'], …]`.
     * Older / theme-overridden configs may return strings or DataObjects
     * instead; we duck-type all three shapes defensively.
     *
     * @return list<array{value: string, label: string}>
     */
    public function getPageLayoutOptions(): array
    {
        $rows = [];
        $config = $this->pageLayoutConfigBuilder->getPageLayoutsConfig();
        foreach ($config->getPageLayouts() as $code => $layout) {
            $rows[] = [
                'value' => (string) $code,
                'label' => $this->extractLayoutLabel($code, $layout),
            ];
        }
        return $rows;
    }

    /** @param mixed $layout */
    private function extractLayoutLabel(mixed $code, mixed $layout): string
    {
        if (is_array($layout)) {
            return (string) ($layout['label'] ?? $code);
        }
        // DataObject exposes getLabel() via __call, which method_exists
        // doesn't see — check the class explicitly first.
        if ($layout instanceof \Magento\Framework\DataObject) {
            $label = (string) $layout->getData('label');
            return $label !== '' ? $label : (string) $code;
        }
        if (is_object($layout) && method_exists($layout, 'getLabel')) {
            return (string) $layout->getLabel();
        }
        if (is_string($layout) && $layout !== '') {
            return $layout;
        }
        return (string) $code;
    }

    /**
     * Mirrors \Magento\Widget\Model\Widget\Instance::$_layoutHandles +
     * the dynamic per-product-type expansion.
     *
     * @return array<string, string>
     */
    public function getLayoutHandleMap(): array
    {
        $map = [
            'all_pages'            => WidgetInstance::DEFAULT_LAYOUT_HANDLE,
            'all_products'         => WidgetInstance::PRODUCT_LAYOUT_HANDLE,
            'anchor_categories'    => WidgetInstance::ANCHOR_CATEGORY_LAYOUT_HANDLE,
            'notanchor_categories' => WidgetInstance::NOTANCHOR_CATEGORY_LAYOUT_HANDLE,
        ];
        foreach ($this->productType->getTypes() as $typeId => $type) {
            $map[$typeId . '_products'] = str_replace(
                '{{TYPE}}',
                (string) $typeId,
                WidgetInstance::PRODUCT_TYPE_LAYOUT_HANDLE,
            );
        }
        return $map;
    }
}
