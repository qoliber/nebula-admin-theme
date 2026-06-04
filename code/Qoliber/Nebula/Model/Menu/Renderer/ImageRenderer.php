<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Model\Menu\Renderer;

use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Qoliber\Nebula\Api\MenuIconRendererInterface;

/**
 * Renders a menu icon as an `<img>` for partner modules that want a logo or
 * a hand-drawn SVG instead of a Font Awesome glyph.
 *
 * `spec[src]` accepts either a fully-qualified URL or a Magento module
 * reference (`Acme_Catalog::images/rocket.svg`) — the latter resolves
 * through the asset repository like any other static view file.
 */
class ImageRenderer implements MenuIconRendererInterface
{
    public function __construct(
        private readonly Escaper $escaper,
        private readonly AssetRepository $assetRepository
    ) {
    }

    public function render(array $spec, string $extra = ''): string
    {
        $src = (string) ($spec['src'] ?? '');
        if ($src === '') {
            return '';
        }

        // `Module::path/to.svg` → resolve via the asset pipeline;
        // anything else (http(s), data:, raw path) goes through as-is.
        if (str_contains($src, '::')) {
            $src = $this->assetRepository->getUrl($src);
        }

        $alt = (string) ($spec['alt'] ?? '');
        $classes = trim($extra);

        $html = '<img src="' . $this->escaper->escapeUrl($src) . '"';
        if ($alt !== '') {
            $html .= ' alt="' . $this->escaper->escapeHtmlAttr($alt) . '"';
        }
        if ($classes !== '') {
            $html .= ' class="' . $this->escaper->escapeHtmlAttr($classes) . '"';
        }
        $html .= '/>';
        return $html;
    }
}
