<?php

declare(strict_types=1);

namespace Qoliber\NebulaPageBuilder\Model\MasterFormat;

use Qoliber\NebulaPageBuilder\Api\MasterFormatParserInterface;
use Qoliber\NebulaPageBuilder\Model\ContentType\Registry;
use Qoliber\NebulaPageBuilder\Model\Converter\Pool;

class Parser implements MasterFormatParserInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Pool $converterPool
    ) {
    }

    public function parse(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<div id="pb-parse-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('pb-parse-root');
        if (!$root) {
            return [];
        }

        return $this->parseChildren($root);
    }

    private function parseChildren(\DOMElement $parent): array
    {
        $nodes = [];

        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $contentType = $child->getAttribute('data-content-type');
            if ($contentType === '') {
                // Check nested — some appearances wrap in an outer div
                $nested = $this->parseChildren($child);
                if (!empty($nested)) {
                    $nodes = array_merge($nodes, $nested);
                }
                continue;
            }

            $appearance = $child->getAttribute('data-appearance') ?: 'default';
            $definition = $this->registry->get($contentType);

            if ($definition === null) {
                continue;
            }

            $data = $this->extractData($child, $definition, $appearance);
            $data['appearance'] = $appearance;

            $node = [
                'id' => $this->generateId(),
                'type' => $contentType,
                'appearance' => $appearance,
                'data' => $data,
                'children' => [],
            ];

            // If container, parse children
            $isContainer = !empty($definition['isContainer']);
            if ($isContainer) {
                $node['children'] = $this->findAndParseChildren($child, $contentType, $appearance);
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    private function extractData(\DOMElement $element, array $definition, string $appearance): array
    {
        $data = [];
        $appearanceDef = $definition['appearances'][$appearance] ?? [];
        $elementDefs = $appearanceDef['elements'] ?? [];

        // Extract from the "main" element (the element with data-content-type)
        $mainDef = $elementDefs['main'] ?? [];

        // Extract attributes
        foreach ($mainDef['attributes'] ?? [] as $attrName => $attrSource) {
            $source = is_array($attrSource) ? ($attrSource['source'] ?? $attrName) : $attrSource;
            $htmlAttr = $source;
            if ($element->hasAttribute($htmlAttr)) {
                $data[$source] = $element->getAttribute($htmlAttr);
            }
        }

        // Extract CSS classes
        if (!empty($mainDef['cssSource'])) {
            $classes = $element->getAttribute('class');
            $data[$mainDef['cssSource']] = $classes;
        }

        // Extract inline styles
        $styleStr = $element->getAttribute('style');
        $parsedStyles = $this->parseStyleString($styleStr);

        foreach ($mainDef['styles'] ?? [] as $styleName => $styleConfig) {
            $source = $styleConfig['source'] ?? $styleName;
            $cssProp = str_replace('_', '-', $styleName);

            if (isset($parsedStyles[$cssProp])) {
                $value = $parsedStyles[$cssProp];
                $converterName = $styleConfig['converter'] ?? null;

                if ($converterName) {
                    $converter = $this->converterPool->get($converterName);
                    if ($converter) {
                        $value = $converter->fromMaster($value, $data);
                    }
                }

                $data[$source] = $value;
            }
        }

        // Extract inner element data if present
        $innerElement = $this->findInnerElement($element, $appearance);
        if ($innerElement && isset($elementDefs['inner'])) {
            $innerDef = $elementDefs['inner'];
            $innerStyleStr = $innerElement->getAttribute('style');
            $innerParsedStyles = $this->parseStyleString($innerStyleStr);

            foreach ($innerDef['styles'] ?? [] as $styleName => $styleConfig) {
                $source = $styleConfig['source'] ?? $styleName;
                $cssProp = str_replace('_', '-', $styleName);

                if (isset($innerParsedStyles[$cssProp])) {
                    $value = $innerParsedStyles[$cssProp];
                    $converterName = $styleConfig['converter'] ?? null;

                    if ($converterName) {
                        $converter = $this->converterPool->get($converterName);
                        if ($converter) {
                            $value = $converter->fromMaster($value, $data);
                        }
                    }

                    $data[$source] = $value;
                }
            }

            // Inner element CSS classes
            if (!empty($innerDef['cssSource'])) {
                $data[$innerDef['cssSource']] = $innerElement->getAttribute('class');
            }

            // Inner element attributes
            foreach ($innerDef['attributes'] ?? [] as $attrName => $attrSource) {
                $source = is_array($attrSource) ? ($attrSource['source'] ?? $attrName) : $attrSource;
                if ($innerElement->hasAttribute($source)) {
                    $data[$source] = $innerElement->getAttribute($source);
                }
            }
        }

        // Extract heading text/tag for heading content type
        if ($definition['name'] === 'heading') {
            $tag = strtolower($element->tagName);
            $data['heading_type'] = $tag;
            $data['heading_text'] = $element->textContent;
        }

        return $data;
    }

    private function findInnerElement(\DOMElement $element, string $appearance): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && !$child->hasAttribute('data-content-type')) {
                return $child;
            }
        }

        return null;
    }

    private function findAndParseChildren(\DOMElement $element, string $contentType, string $appearance): array
    {
        // For contained row, children are inside the inner div
        if ($contentType === 'row' && $appearance === 'contained') {
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMElement && !$child->hasAttribute('data-content-type')) {
                    return $this->parseChildren($child);
                }
            }
        }

        // For full-width row, children inside .row-full-width-inner
        if ($contentType === 'row' && $appearance === 'full-width') {
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMElement && strpos($child->getAttribute('class'), 'row-full-width-inner') !== false) {
                    return $this->parseChildren($child);
                }
            }
        }

        return $this->parseChildren($element);
    }

    private function parseStyleString(string $style): array
    {
        $result = [];
        $parts = array_filter(explode(';', $style));

        foreach ($parts as $part) {
            $colonPos = strpos($part, ':');
            if ($colonPos === false) {
                continue;
            }

            $prop = trim(substr($part, 0, $colonPos));
            $value = trim(substr($part, $colonPos + 1));

            if ($prop !== '' && $value !== '') {
                $result[$prop] = $value;
            }
        }

        return $result;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
