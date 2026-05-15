<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Qoliber\Nebula\Model\Config\Source\CategoryViewType;
use Qoliber\Nebula\Model\Config\Source\MenuPosition;

class Theme
{
    public const XML_PATH_MENU_POSITION = 'nebula/theme/menu_position';
    public const XML_PATH_SKIN = 'nebula/theme/skin';
    public const XML_PATH_ENABLE_PRODUCT_EDIT_TABS = 'nebula/theme/enable_product_edit_tabs';
    public const XML_PATH_ENABLE_CMS_PAGE_EDIT_TABS = 'nebula/theme/enable_cms_page_edit_tabs';
    public const XML_PATH_CATEGORY_VIEW_TYPE = 'nebula/theme/category_view_type';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function getMenuPosition(?string $scopeCode = null, string $scopeType = ScopeInterface::SCOPE_STORE): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_MENU_POSITION,
            $scopeType,
            $scopeCode
        );

        return $value !== '' ? $value : MenuPosition::SIDEBAR;
    }

    public function getSkin(?string $scopeCode = null, string $scopeType = ScopeInterface::SCOPE_STORE): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_SKIN,
            $scopeType,
            $scopeCode
        );
    }

    public function isProductEditTabsEnabled(
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_PRODUCT_EDIT_TABS,
            $scopeType,
            $scopeCode
        );
    }

    public function isCmsPageEditTabsEnabled(
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_CMS_PAGE_EDIT_TABS,
            $scopeType,
            $scopeCode
        );
    }

    public function getCategoryViewType(
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): string {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CATEGORY_VIEW_TYPE,
            $scopeType,
            $scopeCode
        );

        return $value !== '' ? $value : CategoryViewType::STANDARD;
    }

    /**
     * @deprecated Use getCategoryViewType() instead.
     */
    public function isCategoryRefreshEnabled(
        ?string $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        return $this->getCategoryViewType($scopeCode, $scopeType) !== CategoryViewType::STANDARD;
    }
}
