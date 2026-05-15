<?php

declare(strict_types=1);

namespace Qoliber\NebulaUiRemoval\Plugin;

use Magento\Framework\View\Layout\Generator\Context as GeneratorContext;
use Magento\Framework\View\Layout\Generator\UiComponent;
use Magento\Framework\View\Layout\Reader\Context as ReaderContext;
use Qoliber\NebulaComponent\Model\Definition\Loader;

/**
 * Selectively prevents UI Components from being generated when a Nebula replacement exists.
 *
 * Only skips UI Components that have a matching grid or form JSON definition.
 * UI Components without Nebula replacements are left untouched.
 */
class DisableUiComponentGenerator
{
    public function __construct(
        private readonly Loader $loader
    ) {
    }

    public function aroundProcess(
        UiComponent $subject,
        callable $proceed,
        ReaderContext $readerContext,
        GeneratorContext $generatorContext
    ): UiComponent {
        $scheduledStructure = $readerContext->getScheduledStructure();
        $scheduledElements = $scheduledStructure->getElements();

        foreach ($scheduledElements as $elementName => $element) {
            [$elementType] = $element;

            if ($elementType !== 'uiComponent') {
                continue;
            }

            if ($this->hasNebulaReplacement($elementName)) {
                $scheduledStructure->unsetElement($elementName);
            }
        }

        return $proceed($readerContext, $generatorContext);
    }

    private function hasNebulaReplacement(string $componentName): bool
    {
        foreach (['grid', 'form'] as $type) {
            $definitions = $this->loader->load($type, $componentName);
            if (!empty($definitions)) {
                return true;
            }
        }

        return false;
    }
}
