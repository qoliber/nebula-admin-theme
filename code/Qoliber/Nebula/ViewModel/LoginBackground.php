<?php

declare(strict_types=1);

namespace Qoliber\Nebula\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Exposes the admin-login background image + rotation settings to the
 * login_branding.phtml template.
 */
class LoginBackground implements ArgumentInterface
{
    private const XML_ENABLED = 'nebula/login/background_enabled';
    private const XML_ROTATION = 'nebula/login/background_rotation';
    private const XML_IMAGE = 'nebula/login/background_image';

    /** Fallback image shipped with the theme. */
    private const DEFAULT_ASSET = 'Qoliber_Nebula::images/login-background.png';

    /** Media sub-path where uploaded backgrounds land. */
    private const MEDIA_SUBPATH = 'nebula/login/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly AssetRepository $assetRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_ENABLED);
    }

    /**
     * Rotation period in seconds. `0` means no rotation.
     */
    public function getRotationSeconds(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_ROTATION);
        return max(0, $value);
    }

    /**
     * Resolves the background image URL:
     * 1. Admin-uploaded image under pub/media/nebula/login/, or
     * 2. Theme-bundled default asset.
     */
    public function getImageUrl(): string
    {
        $uploaded = (string) $this->scopeConfig->getValue(self::XML_IMAGE);
        if ($uploaded !== '') {
            try {
                $mediaBase = $this->storeManager->getStore()
                    ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                return $mediaBase . self::MEDIA_SUBPATH . ltrim($uploaded, '/');
            } catch (\Throwable) {
                // fall through to theme asset
            }
        }

        return $this->assetRepository->getUrl(self::DEFAULT_ASSET);
    }
}
