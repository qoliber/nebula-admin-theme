<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ViewModel for the `seo_preview` snippet. Supplies the current store's base
 * URL and the entity-aware URL suffix shown in the Google SERP preview.
 *
 * Entity → suffix mapping is injected via di.xml so any module can declare
 * its own entity-type (a blog post, a brand page, …) by adding one line:
 *
 *   <type name="Qoliber\NebulaTheme\ViewModel\SeoPreview">
 *     <arguments>
 *       <argument name="suffixConfigPaths" xsi:type="array">
 *         <item name="acme_blog_post" xsi:type="string">acme_blog/seo/post_url_suffix</item>
 *       </argument>
 *     </arguments>
 *   </type>
 *
 * Unknown entity types resolve to an empty suffix — safer than guessing
 * '.html'. CMS pages have no suffix and so are simply absent from the map.
 */
class SeoPreview implements ArgumentInterface
{
    /**
     * @param array<string, string> $suffixConfigPaths entity_type → scope-config path
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly array $suffixConfigPaths = [],
    ) {
    }

    public function getBaseUrl(): string
    {
        try {
            return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
        } catch (NoSuchEntityException) {
            return '';
        }
    }

    /**
     * URL suffix for `$entityType`. Reads the scope-config path that the
     * registry binds to that entity type. Returns '' when:
     *   - the entity type isn't registered (e.g. cms_page)
     *   - the registered config value is empty
     */
    public function getUrlSuffix(string $entityType): string
    {
        $path = $this->suffixConfigPaths[$entityType] ?? null;
        if ($path === null) {
            return '';
        }

        $suffix = (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);

        // Vendor stores suffixes without the leading dot ('html'). Normalise so
        // the preview renders '/sku.html' rather than '/skuhtml'.
        if ($suffix === '' || str_starts_with($suffix, '.')) {
            return $suffix;
        }

        return '.' . $suffix;
    }
}
