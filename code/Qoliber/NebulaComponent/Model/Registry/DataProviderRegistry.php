<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Registry;

use Qoliber\NebulaComponent\Api\DataProviderInterface;
use Qoliber\NebulaComponent\Api\DataProviderRegistryInterface;

/**
 * @api
 */
class DataProviderRegistry extends AbstractRegistry implements DataProviderRegistryInterface
{
    public function get(string $alias): DataProviderInterface
    {
        /** @var \Qoliber\NebulaComponent\Api\DataProviderInterface $instance */
        $instance = $this->resolve($alias);

        return $instance;
    }

    protected function registryLabel(): string
    {
        return 'data provider';
    }

    protected function assertInstance(string $alias, string $className, object $instance): void
    {
        if (!$instance instanceof DataProviderInterface) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Data provider alias "%1" resolves to %2, which does not implement %3.',
                    $alias,
                    $className,
                    DataProviderInterface::class
                )
            );
        }
    }
}
