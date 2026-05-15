<?php

declare(strict_types=1);

namespace Qoliber\NebulaTheme\Model\Registry;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\TMap;
use Magento\Framework\ObjectManager\TMapFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Qoliber\NebulaTheme\Api\WidgetChooserViewModelInterface;

/**
 * Single source of truth for widget chooser snippets. Holds per-alias
 * metadata that drives:
 *   - helper-block FQCN → alias lookup for ParameterTranslator;
 *   - the host phtml's chooser-mount loop (modal-mode choosers);
 *   - the parameters phtml's inline chooser includes (inline-mode);
 *   - the typed ViewModel resolution passed into each chooser snippet.
 *
 * ViewModel instantiation goes through \Magento\Framework\ObjectManager\TMap
 * (Magento's idiomatic DI pattern for keyed instance maps). TMap enforces
 * WidgetChooserViewModelInterface at lookup time — a mis-registered
 * `view_model` FQCN fails loudly with a typed exception instead of as a
 * vague "method not found" deep inside Alpine.
 *
 * Third-party modules add their own chooser by declaring a `choosers`
 * binding in their own di.xml — no fork of NebulaTheme, no edits to
 * the host phtml or layout XML needed.
 *
 *   <type name="Qoliber\NebulaTheme\Model\Registry\WidgetChooserRegistry">
 *       <arguments>
 *           <argument name="choosers" xsi:type="array">
 *               <item name="cms_block" xsi:type="array">
 *                   <item name="helper_block" xsi:type="string">Magento\Cms\Block\Adminhtml\Block\Widget\Chooser</item>
 *                   <item name="template"     xsi:type="string">Qoliber_NebulaTheme::widget/chooser/cms_block.phtml</item>
 *                   <item name="view_model"   xsi:type="string">Qoliber\NebulaTheme\ViewModel\WidgetChooser\CmsBlock</item>
 *                   <item name="mode"         xsi:type="string">modal</item>  <!-- default; can omit -->
 *               </item>
 *               <item name="conditions" xsi:type="array">
 *                   <item name="template"     xsi:type="string">Qoliber_NebulaTheme::widget/chooser/conditions.phtml</item>
 *                   <item name="view_model"   xsi:type="string">Qoliber\NebulaTheme\ViewModel\WidgetChooser\Conditions</item>
 *                   <item name="mode"         xsi:type="string">inline</item>
 *               </item>
 *           </argument>
 *       </arguments>
 *   </type>
 *
 * `mode: 'modal'` (default) — chooser renders as a sibling Alpine scope
 *   that opens a modal on `open-chooser-<alias>` and dispatches
 *   `chooser:selected`.
 * `mode: 'inline'` — chooser renders inside the parameter row (gets
 *   `field` / `state` in Alpine scope), e.g. the conditions rule editor.
 *
 * `helper_block` may be omitted (e.g. for the `conditions` alias, which
 * is matched via xsi:type, not a helper-block FQCN).
 */
class WidgetChooserRegistry implements ArgumentInterface
{
    /**
     * @var array<string, array{helper_block: string, template: string, view_model: string, mode: 'modal'|'inline'}>
     */
    private array $byAlias;

    /** @var array<string, string> helper-block FQCN → alias */
    private array $helperIndex;

    /** @var TMap<string, WidgetChooserViewModelInterface> alias → resolved view-model instance (lazy) */
    private TMap $viewModels;

    /**
     * @param array<string, array<string, mixed>> $choosers
     */
    public function __construct(
        TMapFactory $tmapFactory,
        array $choosers = [],
    ) {
        $this->byAlias     = $this->sanitize($choosers);
        $this->helperIndex = $this->buildHelperIndex($this->byAlias);

        $viewModelFqcnByAlias = [];
        foreach ($this->byAlias as $alias => $cfg) {
            $viewModelFqcnByAlias[$alias] = $cfg['view_model'];
        }

        $this->viewModels = $tmapFactory->create([
            'array' => $viewModelFqcnByAlias,
            'type'  => WidgetChooserViewModelInterface::class,
        ]);
    }

    /**
     * Resolve the chooser alias for the helper-block FQCN declared on
     * an xsi:type="block" widget parameter. Returns `$fallback`
     * (default 'generic') when no chooser is registered for it.
     */
    public function aliasForHelperBlock(string $helperBlockFqcn, string $fallback = 'generic'): string
    {
        return $this->helperIndex[$helperBlockFqcn] ?? $fallback;
    }

    /**
     * @return array<string, array{helper_block: string, template: string, view_model: string, mode: 'modal'|'inline'}>
     */
    public function all(): array
    {
        return $this->byAlias;
    }

    /**
     * Choosers that render as a sibling modal (the wizard host iterates
     * this to mount each).
     *
     * @return array<string, array{helper_block: string, template: string, view_model: string, mode: 'modal'|'inline'}>
     */
    public function modal(): array
    {
        return array_filter($this->byAlias, static fn (array $cfg) => $cfg['mode'] === 'modal');
    }

    /**
     * Choosers that render inline inside the parameter row (the
     * parameters phtml iterates this).
     *
     * @return array<string, array{helper_block: string, template: string, view_model: string, mode: 'modal'|'inline'}>
     */
    public function inline(): array
    {
        return array_filter($this->byAlias, static fn (array $cfg) => $cfg['mode'] === 'inline');
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return array_keys($this->byAlias);
    }

    public function has(string $alias): bool
    {
        return isset($this->byAlias[$alias]);
    }

    /**
     * @return array{helper_block: string, template: string, view_model: string, mode: 'modal'|'inline'}|null
     */
    public function get(string $alias): ?array
    {
        return $this->byAlias[$alias] ?? null;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException when the
     *         alias is unknown. (TMap enforces the WidgetChooserViewModelInterface
     *         contract at access time — a registered view_model that
     *         doesn't implement it throws Magento's own ConfigurationMismatchException.)
     */
    public function resolveViewModel(string $alias): WidgetChooserViewModelInterface
    {
        if (!isset($this->byAlias[$alias])) {
            throw new LocalizedException(
                __('No widget chooser is registered for alias "%1".', $alias),
            );
        }
        return $this->viewModels[$alias];
    }

    /**
     * @param array<int|string, mixed> $raw
     * @return array<string, array{helper_block: string, template: string, view_model: string, mode: 'modal'|'inline'}>
     */
    private function sanitize(array $raw): array
    {
        $clean = [];
        foreach ($raw as $alias => $cfg) {
            if (!is_string($alias) || $alias === '' || !is_array($cfg)) {
                continue;
            }
            $helper    = (string) ($cfg['helper_block'] ?? '');
            $template  = (string) ($cfg['template']     ?? '');
            $viewModel = (string) ($cfg['view_model']   ?? '');
            $mode      = (string) ($cfg['mode']         ?? 'modal');
            if ($template === '' || $viewModel === '') {
                continue;
            }
            if ($mode !== 'modal' && $mode !== 'inline') {
                $mode = 'modal';
            }
            $clean[$alias] = [
                'helper_block' => $helper,
                'template'     => $template,
                'view_model'   => $viewModel,
                'mode'         => $mode,
            ];
        }
        return $clean;
    }

    /**
     * @param array<string, array{helper_block: string, template: string, view_model: string, mode: string}> $byAlias
     * @return array<string, string>
     */
    private function buildHelperIndex(array $byAlias): array
    {
        $idx = [];
        foreach ($byAlias as $alias => $cfg) {
            if ($cfg['helper_block'] !== '') {
                $idx[$cfg['helper_block']] = $alias;
            }
        }
        return $idx;
    }
}
