<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Api;

/**
 * One-icon-type contract used by the Nebula sidebar to render menu icons.
 *
 * The {@see \Qoliber\Nebula\Model\Menu\MenuIconRegistry} dispatches per
 * `type` declared in di.xml. Built-in implementors ship for `fontawesome`
 * (the default) and `image`. Custom modules add new types — e.g.
 * `inline-svg`, `lucide`, `brand-logo` — by registering an implementation
 * under a new `type` key without touching the block or template.
 */
interface MenuIconRendererInterface
{
    /**
     * Render a single icon as an HTML snippet to embed in the menu.
     *
     * @param array<string, string> $spec   the rule definition from di.xml:
     *                                       at minimum a `type`, plus
     *                                       type-specific fields like
     *                                       `class` (fontawesome) or `src`
     *                                       (image)
     * @param string                $extra  Tailwind / CSS class string the
     *                                       template wants appended to the
     *                                       outer icon element (sizing,
     *                                       opacity, alignment — concerns
     *                                       that belong to the slot, not
     *                                       the icon definition)
     */
    public function render(array $spec, string $extra = ''): string;
}
