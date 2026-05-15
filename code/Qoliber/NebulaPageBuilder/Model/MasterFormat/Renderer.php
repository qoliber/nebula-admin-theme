<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\MasterFormat;

use Magento\Framework\View\LayoutInterface;
use Qoliber\NebulaPageBuilder\Api\MasterFormatRendererInterface;
use Qoliber\NebulaPageBuilder\Model\ContentType\Registry;
use Qoliber\NebulaPageBuilder\Model\Converter\Pool;

class Renderer implements MasterFormatRendererInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Pool $converterPool,
        private readonly LayoutInterface $layout
    ) {
    }

    public function render(array $tree): string
    {
        $html = '';
        foreach ($tree as $node) {
            $html .= $this->renderNode($node);
        }

        return $html;
    }

    private function renderNode(array $node): string
    {
        $type = $node['type'] ?? '';
        $definition = $this->registry->get($type);

        if ($definition === null) {
            return '';
        }

        $data = $node['data'] ?? [];
        $appearance = $data['appearance'] ?? $definition['defaults']['appearance'] ?? $this->getDefaultAppearance($definition);
        $appearances = $definition['appearances'] ?? [];
        $appearanceDef = $appearances[$appearance] ?? [];
        $masterTemplate = $appearanceDef['masterTemplate'] ?? '';

        if (empty($masterTemplate)) {
            return '';
        }

        // Build element styles and attributes from definition
        $elements = $this->buildElements($appearanceDef, $data);

        // Render children
        $childrenHtml = '';
        if (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                $childrenHtml .= $this->renderNode($child);
            }
        }

        // Render template
        $block = $this->layout->createBlock(\Qoliber\NebulaPageBuilder\Block\MasterTemplate::class);
        $block->setTemplate('Qoliber_NebulaPageBuilder::' . $masterTemplate);
        $block->setData('node_data', $data);
        $block->setData('elements', $elements);
        $block->setData('children_html', $childrenHtml);
        $block->setData('content_type', $type);
        $block->setData('appearance', $appearance);

        return $block->toHtml();
    }

    private function buildElements(array $appearanceDef, array $data): array
    {
        $elements = [];
        $elementDefs = $appearanceDef['elements'] ?? [];

        foreach ($elementDefs as $elementName => $elementDef) {
            $styles = [];
            $attributes = [];
            $cssClasses = [];

            // Process styles
            foreach ($elementDef['styles'] ?? [] as $styleName => $styleConfig) {
                $source = $styleConfig['source'] ?? $styleName;
                $value = $data[$source] ?? '';
                $converterName = $styleConfig['converter'] ?? null;

                if ($converterName && $value !== '') {
                    $converter = $this->converterPool->get($converterName);
                    if ($converter) {
                        $value = $converter->toMaster($value, $data);
                    }
                }

                if ($value !== '' && $value !== null) {
                    $cssProp = str_replace('_', '-', $styleName);
                    $styles[$cssProp] = $value;
                }
            }

            // Process static styles
            foreach ($elementDef['staticStyles'] ?? [] as $styleName => $value) {
                $cssProp = str_replace('_', '-', $styleName);
                $styles[$cssProp] = $value;
            }

            // Process attributes
            foreach ($elementDef['attributes'] ?? [] as $attrName => $attrSource) {
                if (is_array($attrSource)) {
                    $source = $attrSource['source'] ?? $attrName;
                    $value = $data[$source] ?? '';
                } else {
                    $value = $data[$attrSource] ?? '';
                }

                if ($value !== '' && $value !== null) {
                    $attributes[$attrName] = (string) $value;
                }
            }

            // Process CSS classes
            if (!empty($elementDef['cssSource'])) {
                $cssValue = $data[$elementDef['cssSource']] ?? '';
                if ($cssValue) {
                    $cssClasses = array_filter(explode(' ', (string) $cssValue));
                }
            }

            // Add static CSS classes
            if (!empty($elementDef['cssClasses'])) {
                $cssClasses = array_merge($cssClasses, $elementDef['cssClasses']);
            }

            $elements[$elementName] = [
                'styles' => $styles,
                'attributes' => $attributes,
                'cssClasses' => $cssClasses,
                'tag' => $elementDef['tag'] ?? 'div',
            ];
        }

        return $elements;
    }

    private function getDefaultAppearance(array $definition): string
    {
        foreach ($definition['appearances'] ?? [] as $name => $appearance) {
            if (!empty($appearance['default'])) {
                return $name;
            }
        }

        $keys = array_keys($definition['appearances'] ?? []);
        return $keys[0] ?? 'default';
    }
}
