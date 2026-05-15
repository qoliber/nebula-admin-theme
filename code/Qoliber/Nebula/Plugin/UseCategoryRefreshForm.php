<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Plugin;

use Qoliber\Nebula\Model\Config\Source\CategoryViewType;
use Qoliber\Nebula\Model\Config\Theme;
use Qoliber\NebulaForm\Block\EavForm;

/**
 * Swap the category edit form definition based on the configured view type.
 */
class UseCategoryRefreshForm
{
    private const DEFAULT_FORM_ID = 'catalog_category_edit';
    private const REFRESHED_FORM_ID = 'catalog_category_edit_refresh';
    private const UNIFIED_FORM_ID = 'catalog_category_edit_unified';
    private const TABBED_SIDEBAR_FORM_ID = 'catalog_category_edit_tabbed_sidebar';

    public function __construct(
        private readonly Theme $themeConfig,
    ) {
    }

    public function afterGetFormId(EavForm $subject, string $result): string
    {
        if ($result !== self::DEFAULT_FORM_ID) {
            return $result;
        }

        $viewType = $this->themeConfig->getCategoryViewType();

        return match ($viewType) {
            CategoryViewType::REFRESHED => self::REFRESHED_FORM_ID,
            CategoryViewType::UNIFIED => self::UNIFIED_FORM_ID,
            CategoryViewType::TABBED_SIDEBAR => self::TABBED_SIDEBAR_FORM_ID,
            default => $result,
        };
    }
}
