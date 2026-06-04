<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Qoliber\NebulaForm\Block\Form;

/**
 * Swap the CMS block edit form definition to the tabbed variant when enabled.
 */
class UseTabbedCmsBlockEditForm
{
    private const XML_PATH_ENABLE_CMS_BLOCK_EDIT_TABS = 'nebula/theme/enable_cms_block_edit_tabs';
    private const DEFAULT_FORM_ID = 'cms_block_edit';
    private const TABBED_FORM_ID = 'cms_block_edit_tabs';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function afterGetFormId(Form $subject, string $result): string
    {
        if ($result !== self::DEFAULT_FORM_ID) {
            return $result;
        }

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_CMS_BLOCK_EDIT_TABS)) {
            return $result;
        }

        return self::TABBED_FORM_ID;
    }
}
