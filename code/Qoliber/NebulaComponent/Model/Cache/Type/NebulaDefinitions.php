<?php

declare(strict_types=1);

namespace Qoliber\NebulaComponent\Model\Cache\Type;

/**
 * Dedicated Magento cache type for compiled Nebula grid/form/snippet/content_type definitions.
 *
 * Flush with: bin/magento cache:clean nebula_definitions
 */
class NebulaDefinitions extends \Magento\Framework\Cache\Frontend\Decorator\TagScope
{
    /** @var string cache type code (matches etc/cache.xml) */
    public const TYPE_IDENTIFIER = 'nebula_definitions';

    /** @var string tag attached to every entry of this cache type */
    public const CACHE_TAG = 'NEBULA_DEFINITIONS';

    public function __construct(
        \Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool
    ) {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
