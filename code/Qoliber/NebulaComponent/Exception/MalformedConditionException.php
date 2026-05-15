<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Exception;

/**
 * Thrown when a JSON definition carries a condition node that is not shaped
 * according to the Nebula condition DSL.
 *
 * @api
 */
class MalformedConditionException extends \Magento\Framework\Exception\LocalizedException
{
}
