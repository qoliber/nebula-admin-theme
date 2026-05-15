<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Exception;

/**
 * Thrown when a JSON definition references an alias that no module registered.
 *
 * @api
 */
class UnknownAliasException extends \Magento\Framework\Exception\LocalizedException
{
    public static function forRegistry(string $registry, string $alias, array $known = []): self
    {
        $hint = $known === []
            ? ''
            : ' Known aliases: ' . implode(', ', array_slice($known, 0, 20))
                . (count($known) > 20 ? ' …' : '');

        return new self(
            __(
                'Nebula %1 alias "%2" is not registered.%3',
                $registry,
                $alias,
                $hint
            )
        );
    }
}
