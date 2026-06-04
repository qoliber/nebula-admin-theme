<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Model\Menu\Renderer;

use Magento\Framework\Escaper;
use Qoliber\Nebula\Api\MenuIconRendererInterface;

/**
 * Renders a Font Awesome menu icon: `<i class="{spec[class]} {extra}"></i>`.
 *
 * `class` carries the FA token (`fa-solid fa-gauge-high`); `extra` is the
 * slot-specific styling the template hands in (`w-5 text-center
 * opacity-70`). Both halves are escaped against attribute injection.
 */
class FontawesomeRenderer implements MenuIconRendererInterface
{
    public function __construct(
        private readonly Escaper $escaper
    ) {
    }

    public function render(array $spec, string $extra = ''): string
    {
        $classes = trim(($spec['class'] ?? '') . ' ' . $extra);
        if ($classes === '') {
            return '';
        }
        return '<i class="' . $this->escaper->escapeHtmlAttr($classes) . '"></i>';
    }
}
