<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * DI-seeded lookup: snippet id → ViewModel instance.
 *
 * Lets snippet templates receive a typed ViewModel without the template
 * having to resolve it through ObjectManager. {@see \Qoliber\NebulaForm\Model\Form\SectionRenderer}
 * consults this registry when rendering snippets and injects the matching
 * ViewModel into the template's data bag under `view_model`.
 *
 * Register mappings per module in `etc/di.xml`:
 * <type name="Qoliber\NebulaComponent\Model\SnippetViewModelRegistry">
 *     <arguments>
 *         <argument name="viewModels" xsi:type="array">
 *             <item name="customer_cart" xsi:type="object">Qoliber\NebulaCustomer\ViewModel\CustomerCart</item>
 *         </argument>
 *     </arguments>
 * </type>
 */
class SnippetViewModelRegistry
{
    /**
     * @param array<string, \Magento\Framework\View\Element\Block\ArgumentInterface> $viewModels
     */
    public function __construct(
        private array $viewModels = []
    ) {
    }

    public function get(string $snippetId): ?ArgumentInterface
    {
        return $this->viewModels[$snippetId] ?? null;
    }

    public function has(string $snippetId): bool
    {
        return isset($this->viewModels[$snippetId]);
    }
}
