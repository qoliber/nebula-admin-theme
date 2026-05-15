<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Registry;

use Qoliber\NebulaComponent\Api\FilterOptionSourceRegistryInterface;

/**
 * Permissive variant of {@see OptionSourceRegistry}: any object exposing
 * `toOptionArray()` is accepted. Magento ships several filter sources that
 * predate \Magento\Framework\Data\OptionSourceInterface (e.g.
 * \Magento\Store\Model\System\Store); we tolerate them without wrapping.
 *
 * @api
 */
class FilterOptionSourceRegistry extends AbstractRegistry implements FilterOptionSourceRegistryInterface
{
    public function get(string $alias): object
    {
        return $this->resolve($alias);
    }

    protected function registryLabel(): string
    {
        return 'filter option source';
    }

    protected function assertInstance(string $alias, string $className, object $instance): void
    {
        if (!method_exists($instance, 'toOptionArray')) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Filter option source alias "%1" resolves to %2, which does not expose toOptionArray().',
                    $alias,
                    $className
                )
            );
        }
    }
}
