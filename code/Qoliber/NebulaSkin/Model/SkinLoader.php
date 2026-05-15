<?php

declare(strict_types=1);

namespace Qoliber\NebulaSkin\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Loads skin presets from all modules.
 * Scans: view/adminhtml/nebula/skin/*.json in every module.
 *
 * JSON format supports:
 * - "colors"       → --color-nebula-* overrides
 * - "dark"         → dark mode color overrides
 * - "density"      → spacing, font sizes, radius, sidebar widths
 * - "shadows"      → shadow overrides
 * - "transitions"  → animation speed overrides
 * - "accessibility"→ status color overrides for color-blind users
 * - "custom"       → any arbitrary CSS variables
 */
class SkinLoader
{
    /** @var string[] Sections in JSON that map to CSS variables */
    private const CSS_SECTIONS = ['colors', 'density', 'shadows', 'transitions', 'accessibility', 'custom'];

    /**
     * Hard cap on skin-file size. Real skin JSONs are <1 KiB; the cap is a
     * defense-in-depth ceiling against a malformed or maliciously oversized
     * file in any module's view/adminhtml/nebula/skin/ directory.
     */
    private const MAX_SKIN_SIZE = 262144; // 256 KiB

    /** @var array<string, mixed>|null */
    private ?array $skins = null;

    public function __construct(
        private readonly Json $json,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleDirReader $dirReader,
        private readonly ModuleListInterface $moduleList,
    ) {
    }

    public function getAvailableSkins(): array
    {
        if ($this->skins !== null) {
            return $this->skins;
        }

        $this->skins = [];

        foreach ($this->moduleList->getNames() as $moduleName) {
            $moduleDir = $this->dirReader->getModuleDir('', $moduleName);
            $files = glob($moduleDir . '/view/adminhtml/nebula/skin/*.json') ?: [];

            foreach ($files as $file) {
                try {
                    // Read at most MAX_SKIN_SIZE bytes. A truncated read will
                    // fail JSON parsing and fall into the catch — better than
                    // letting a 500MB file blow PHP memory.
                    $content = file_get_contents($file, false, null, 0, self::MAX_SKIN_SIZE);
                    if ($content === false || $content === '') {
                        continue;
                    }
                    $skin = $this->json->unserialize($content);

                    if (isset($skin['id'])) {
                        $skin['label'] = $skin['label'] ?? ucfirst($skin['id']);
                        $this->skins[$skin['id']] = $skin;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $this->skins;
    }

    public function getActiveSkinId(): string
    {
        return $this->scopeConfig->getValue('nebula/theme/skin') ?: 'indigo';
    }

    /**
     * Generate complete inline CSS for the active skin.
     * Outputs :root {} for light mode and .dark {} for dark mode overrides.
     */
    public function getInlineCss(): string
    {
        $skinId = $this->getActiveSkinId();

        $skins = $this->getAvailableSkins();
        $skin = $skins[$skinId] ?? null;

        if (!$skin) {
            return '';
        }

        $css = '';

        // Light mode variables
        $lightVars = $this->collectVariables($skin);
        if ($lightVars) {
            $css .= ':root{' . $lightVars . '}';
        }

        // Dark mode overrides
        if (!empty($skin['dark'])) {
            $darkVars = $this->buildVarString($skin['dark']);
            if ($darkVars) {
                $css .= '.dark{' . $darkVars . '}';
            }
        }

        return $css;
    }

    /**
     * Collect all CSS variables from the skin's sections.
     */
    private function collectVariables(array $skin): string
    {
        $vars = [];

        foreach (self::CSS_SECTIONS as $section) {
            if (!empty($skin[$section]) && is_array($skin[$section])) {
                foreach ($skin[$section] as $prop => $value) {
                    $vars[] = $prop . ':' . $value;
                }
            }
        }

        return implode(';', $vars);
    }

    private function buildVarString(array $variables): string
    {
        $parts = [];
        foreach ($variables as $prop => $value) {
            $parts[] = $prop . ':' . $value;
        }

        return implode(';', $parts);
    }
}
