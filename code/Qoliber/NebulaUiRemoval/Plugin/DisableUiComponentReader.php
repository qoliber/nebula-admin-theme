<?php

declare(strict_types=1);

namespace Qoliber\NebulaUiRemoval\Plugin;

use Magento\Framework\View\Layout\Element;
use Magento\Framework\View\Layout\Reader\Context;
use Magento\Framework\View\Layout\Reader\UiComponent;

/**
 * Prevents UI Components from being read/scheduled from layout XML.
 * Belt-and-suspenders approach — even if the generator somehow runs,
 * no UI Components will be scheduled.
 */
class DisableUiComponentReader
{
    /**
     * Skip interpreting <uiComponent> layout XML elements.
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
        // Don't call $proceed() — skip reading UI Component from layout XML
        return $subject;
    }
}
