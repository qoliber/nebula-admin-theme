<?php

declare(strict_types=1);

namespace Qoliber\NebulaQuill\Setup\Patch\Data;

use Magento\Cms\Model\Wysiwyg\Config as WysiwygConfig;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Ui\Model\Config as UiConfig;
use Qoliber\NebulaQuill\Model\Config\QuillConfig;

class ConfigureDefaultEditor implements DataPatchInterface
{
    private const PAGEBUILDER_ENABLED_CONFIG_PATH = 'cms/pagebuilder/enabled';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        try {
            $this->configWriter->save(
                WysiwygConfig::WYSIWYG_STATUS_CONFIG_PATH,
                WysiwygConfig::WYSIWYG_ENABLED
            );
            $this->configWriter->save(
                UiConfig::WYSIWYG_EDITOR_CONFIG_PATH,
                QuillConfig::EDITOR_ADAPTER_PATH
            );
            $this->configWriter->save(self::PAGEBUILDER_ENABLED_CONFIG_PATH, '0');
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
