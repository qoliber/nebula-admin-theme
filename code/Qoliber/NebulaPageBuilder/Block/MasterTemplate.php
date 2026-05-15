<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Block;

use Magento\Framework\View\Element\Template;

class MasterTemplate extends Template
{
    /**
     * @return array<string, mixed>
     */
    public function getNodeData(): array
    {
        return $this->getData('node_data') ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getElements(): array
    {
        return $this->getData('elements') ?? [];
    }

    public function getChildrenHtml(): string
    {
        return (string) ($this->getData('children_html') ?? '');
    }

    public function getContentType(): string
    {
        return (string) ($this->getData('content_type') ?? '');
    }

    public function getAppearance(): string
    {
        return (string) ($this->getData('appearance') ?? '');
    }

    /**
     * Build inline style string from element styles array.
     */
    public function buildStyleString(array $styles): string
    {
        $parts = [];
        foreach ($styles as $prop => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $prop . ': ' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Build attribute string from element attributes array.
     */
    public function buildAttributeString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            $parts[] = $this->escapeHtmlAttr($name) . '="' . $this->escapeHtmlAttr((string) $value) . '"';
        }

        return implode(' ', $parts);
    }
}
