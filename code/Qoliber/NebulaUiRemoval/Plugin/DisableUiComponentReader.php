<?php

declare(strict_types=1);

namespace Qoliber\NebulaUiRemoval\Plugin;

use Magento\Framework\View\Layout\Element;
use Magento\Framework\View\Layout\Reader\Context;
use Magento\Framework\View\Layout\Reader\UiComponent;
use Qoliber\NebulaComponent\Model\Definition\Loader;

/**
 * Selectively prevents UI Components from being read/scheduled from layout XML.
 *
 * Only skips a `<uiComponent>` when Nebula ships a matching grid/form JSON
 * definition for it; every other UI Component (core or third-party that Nebula
 * does NOT replace) is read normally and renders as stock Magento. This mirrors
 * {@see DisableUiComponentGenerator} so the two plugins agree — without this
 * scoping the reader would blanket-skip every UI Component and any admin screen
 * Nebula does not reimplement would render empty.
 */
class DisableUiComponentReader
{
    /**
     * @param \Qoliber\NebulaComponent\Model\Definition\Loader $loader
     */
    public function __construct(
        private readonly Loader $loader
    ) {
    }

    /**
     * Skip a `<uiComponent>` element only when a Nebula replacement exists.
     *
     * Otherwise defer to the core reader so non-replaced components render.
     *
     * @param \Magento\Framework\View\Layout\Reader\UiComponent $subject
     * @param callable $proceed
     * @param \Magento\Framework\View\Layout\Reader\Context $readerContext
     * @param \Magento\Framework\View\Layout\Element $currentElement
     * @return \Magento\Framework\View\Layout\Reader\UiComponent
     */
    public function aroundInterpret(
        UiComponent $subject,
        callable $proceed,
        Context $readerContext,
        Element $currentElement
    ): UiComponent {
        $name = (string) $currentElement->getAttribute('name');

        if ($name !== '' && $this->hasNebulaReplacement($name)) {
            // Nebula reimplements this component — skip reading it so the stock
            // UI Component is never scheduled.
            return $subject;
        }

        $proceed($readerContext, $currentElement);

        return $subject;
    }

    /**
     * Whether Nebula ships a grid/form JSON definition matching the component.
     *
     * @param string $componentName
     * @return bool
     */
    private function hasNebulaReplacement(string $componentName): bool
    {
        foreach (['grid', 'form'] as $type) {
            if ($this->loader->load($type, $componentName) !== []) {
                return true;
            }
        }

        return false;
    }
}
