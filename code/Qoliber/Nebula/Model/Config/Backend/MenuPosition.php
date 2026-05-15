<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Model\Config\Backend;

use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\Type\Layout as LayoutCache;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Value;

class MenuPosition extends Value
{
    private TypeListInterface $localCacheTypeList;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        $this->localCacheTypeList = $cacheTypeList;

        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function afterSave(): self
    {
        parent::afterSave();

        if (!$this->isValueChanged()) {
            return $this;
        }

        foreach ([ConfigCache::TYPE_IDENTIFIER, LayoutCache::TYPE_IDENTIFIER, BlockCache::TYPE_IDENTIFIER] as $type) {
            $this->localCacheTypeList->cleanType($type);
        }

        return $this;
    }
}
