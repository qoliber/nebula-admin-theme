<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Model\Menu;

use Qoliber\Nebula\Api\MenuIconRendererInterface;

/**
 * Single source of truth for Nebula sidebar icons.
 *
 * Three DI-populated arguments:
 *
 *   - `defaultType`  fallback type a per-item entry inherits when it omits
 *                    its own `type` (built-in: `fontawesome`).
 *   - `default`      the spec used when a menu item id has no registered
 *                    entry. Built-in: a generic dot.
 *   - `icons`        map of menu-item id → spec. Each spec carries at
 *                    least the type-specific fields the matching renderer
 *                    needs; `type` is optional and falls back to
 *                    `defaultType`.
 *   - `renderers`    map of type → MenuIconRendererInterface. New icon
 *                    types are added by registering an implementation
 *                    here from any module's etc/di.xml.
 *
 * Custom modules extend any of the above maps via their own `etc/di.xml`:
 *
 *   <type name="Qoliber\Nebula\Model\Menu\MenuIconRegistry">
 *       <arguments>
 *           <argument name="icons" xsi:type="array">
 *               <item name="Acme_Catalog::dashboard" xsi:type="array">
 *                   <item name="class" xsi:type="string">fa-solid fa-rocket</item>
 *               </item>
 *               <item name="Acme_Catalog::brand" xsi:type="array">
 *                   <item name="type" xsi:type="string">image</item>
 *                   <item name="src"  xsi:type="string">Acme_Catalog::images/logo.svg</item>
 *                   <item name="alt"  xsi:type="string">Acme</item>
 *               </item>
 *           </argument>
 *       </arguments>
 *   </type>
 *
 * No JS rebuild, no template edit — `Menu::renderIcon($id, $extraClass)`
 * picks the new entry up on the next request after `cache:clean config`.
 */
class MenuIconRegistry
{
    /** @var array<string, array<string, string>> */
    private array $icons;

    /** @var array<string, string> */
    private array $default;

    /** @var array<string, MenuIconRendererInterface> */
    private array $renderers;

    /**
     * @param array<string, array<string, string>>      $icons
     * @param array<string, string>                     $default
     * @param array<string, MenuIconRendererInterface>  $renderers
     */
    public function __construct(
        array $icons = [],
        array $default = [],
        private readonly string $defaultType = 'fontawesome',
        array $renderers = []
    ) {
        $this->icons = $icons;
        $this->default = $default;
        $this->renderers = $renderers;
    }

    /**
     * Resolve an item id to its icon spec (with `type` filled in). Returns
     * the configured default when no entry matches; returns an empty array
     * only when the default is also empty.
     *
     * @return array<string, string>
     */
    public function resolve(string $itemId): array
    {
        $spec = $this->icons[$itemId] ?? $this->default;
        if ($spec === []) {
            return [];
        }
        if (!isset($spec['type']) || $spec['type'] === '') {
            $spec['type'] = $this->defaultType;
        }
        return $spec;
    }

    /**
     * @return array<string, string>
     */
    public function getDefault(): array
    {
        return $this->default;
    }

    public function hasIcon(string $itemId): bool
    {
        return isset($this->icons[$itemId]);
    }

    /**
     * Render the icon for a menu item to its full HTML element, ready to
     * drop into the template. Returns an empty string when the spec is
     * empty or the type has no renderer registered.
     */
    public function render(string $itemId, string $extra = ''): string
    {
        $spec = $this->resolve($itemId);
        if ($spec === []) {
            return '';
        }
        $renderer = $this->renderers[$spec['type']] ?? null;
        if ($renderer === null) {
            // Unknown type — soft fail rather than fataling out the whole
            // page render. A misconfigured icon shouldn't break the menu.
            return '';
        }
        return $renderer->render($spec, $extra);
    }
}
