<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Registry;

use Qoliber\NebulaComponent\Api\FormSaveHandlerInterface;
use Qoliber\NebulaComponent\Api\SaveHandlerRegistryInterface;

/**
 * @api
 */
class SaveHandlerRegistry extends AbstractRegistry implements SaveHandlerRegistryInterface
{
    public function get(string $alias): FormSaveHandlerInterface
    {
        /** @var \Qoliber\NebulaComponent\Api\FormSaveHandlerInterface $instance */
        $instance = $this->resolve($alias);

        return $instance;
    }

    protected function registryLabel(): string
    {
        return 'save handler';
    }

    protected function assertInstance(string $alias, string $className, object $instance): void
    {
        if (!$instance instanceof FormSaveHandlerInterface) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Save handler alias "%1" resolves to %2, which does not implement %3.',
                    $alias,
                    $className,
                    FormSaveHandlerInterface::class
                )
            );
        }
    }
}
