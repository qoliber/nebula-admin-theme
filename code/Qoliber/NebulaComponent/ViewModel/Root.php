<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Qoliber\NebulaSkin\Model\SkinLoader;

/**
 * ViewModel for Nebula's root template (Magento_Theme::root.phtml override).
 *
 * This is the canonical example of how ViewModels replace ObjectManager
 * lookups in Nebula phtml templates. Templates resolve the ViewModel via
 * `$block->getData('view_model')` after it's been bound via layout XML.
 */
class Root implements ArgumentInterface
{
    public function __construct(
        private readonly SkinLoader $skinLoader
    ) {
    }

    public function getInlineSkinCss(): string
    {
        return $this->skinLoader->getInlineCss();
    }
}
