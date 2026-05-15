<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\Registry;

/**
 * Curated list of storefront container references used as the fallback
 * Container dropdown options for widgets that declare no <containers>
 * block in their widget.xml (e.g., cms_static_block, cms_page_link).
 *
 * Read by \Qoliber\NebulaTheme\ViewModel\Widget\ContainerCatalog.
 *
 * Bindings come from di.xml so themes / 3rd-party modules can extend
 * the fallback set without monkey-patching:
 *
 *   <type name="Qoliber\NebulaTheme\Model\Registry\WidgetContainerFallbackRegistry">
 *       <arguments>
 *           <argument name="containers" xsi:type="array">
 *               <item name="content"      xsi:type="string">content</item>
 *               <item name="sidebar.main" xsi:type="string">sidebar.main</item>
 *               ...
 *           </argument>
 *       </arguments>
 *   </type>
 *
 * The list values (not the keys) are the actual container references
 * shown in the Container dropdown.
 */
class WidgetContainerFallbackRegistry
{
    /** @var list<string> */
    private array $containers;

    /**
     * @param array<int|string, mixed> $containers
     */
    public function __construct(array $containers = [])
    {
        $this->containers = $this->sanitize($containers);
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->containers;
    }

    /**
     * @param array<int|string, mixed> $raw
     * @return list<string>
     */
    private function sanitize(array $raw): array
    {
        $clean = [];
        foreach ($raw as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }
            $clean[] = $entry;
        }
        return array_values(array_unique($clean));
    }
}
