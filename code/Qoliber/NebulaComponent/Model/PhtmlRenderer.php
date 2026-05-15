<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;

/**
 * Helper: render a phtml template on a transient block created in the layout,
 * with arbitrary data bag. Consolidates the 4-5 copy-pasted blocks of
 * `$layout->createBlock(Template::class)->setTemplate(...)->setData(...)->toHtml()`
 * scattered across Grid and Form blocks.
 */
class PhtmlRenderer
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(LayoutInterface $layout, string $template, array $data = []): string
    {
        if ($template === '') {
            return '';
        }

        /** @var \Magento\Framework\View\Element\Template $block */
        $block = $layout->createBlock(Template::class);

        if (str_contains($template, '::')) {
            $block->setData('module_name', explode('::', $template, 2)[0]);
        }

        $block->setTemplate($template);

        foreach ($data as $key => $value) {
            $block->setData($key, $value);
        }

        return $block->toHtml();
    }
}
