<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Qoliber\NebulaForm\Block\EavForm;

/**
 * Swap the product edit form definition to the tabbed variant when enabled.
 */
class UseTabbedProductEditForm
{
    private const XML_PATH_ENABLE_PRODUCT_EDIT_TABS = 'nebula/theme/enable_product_edit_tabs';
    private const DEFAULT_FORM_ID = 'catalog_product_edit';
    private const TABBED_FORM_ID = 'catalog_product_edit_tabs';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function afterGetFormId(EavForm $subject, string $result): string
    {
        if ($result !== self::DEFAULT_FORM_ID) {
            return $result;
        }

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_PRODUCT_EDIT_TABS)) {
            return $result;
        }

        return self::TABBED_FORM_ID;
    }
}
