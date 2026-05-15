<?php

declare(strict_types=1);

namespace Qoliber\NebulaQuill\Model\Config;

use Magento\Cms\Model\Wysiwyg\Config as WysiwygConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Ui\Model\Config as UiConfig;

class QuillConfig
{
    public const EDITOR_ADAPTER_PATH = 'Qoliber_NebulaQuill/js/wysiwyg/quill-adapter';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WysiwygConfig $wysiwygConfig
    ) {
    }

    public function isSelected(): bool
    {
        return (string) $this->scopeConfig->getValue(UiConfig::WYSIWYG_EDITOR_CONFIG_PATH) === self::EDITOR_ADAPTER_PATH;
    }

    public function isEnabled(): bool
    {
        return $this->wysiwygConfig->isEnabled();
    }

    public function shouldUseQuill(): bool
    {
        return $this->isEnabled() && $this->isSelected();
    }
}
