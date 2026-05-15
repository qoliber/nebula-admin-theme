<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Registry;

use Magento\Framework\Data\OptionSourceInterface;
use Qoliber\NebulaComponent\Api\OptionSourceRegistryInterface;

/**
 * @api
 */
class OptionSourceRegistry extends AbstractRegistry implements OptionSourceRegistryInterface
{
    public function get(string $alias): OptionSourceInterface
    {
        /** @var \Magento\Framework\Data\OptionSourceInterface $instance */
        $instance = $this->resolve($alias);

        return $instance;
    }

    protected function registryLabel(): string
    {
        return 'option source';
    }

    protected function assertInstance(string $alias, string $className, object $instance): void
    {
        if (!$instance instanceof OptionSourceInterface) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Option source alias "%1" resolves to %2, which does not implement %3.',
                    $alias,
                    $className,
                    OptionSourceInterface::class
                )
            );
        }
    }
}
