<?php

declare(strict_types=1);

namespace Qoliber\NebulaForm\Plugin;

use Magento\Framework\View\Layout\ProcessorInterface;
use Qoliber\NebulaForm\Model\Form\RouteFormMap;

/**
 * Generic JSON-driven form wirer.
 *
 * Will replace the 14 hand-written per-page layout XML files that mount a Nebula
 * form onto a Magento admin page, once those legacy layout XMLs are removed.
 * Each form JSON definition self-describes its wiring via `route` / `replaces`
 * keys ({@see RouteFormMap}); this plugin reads that map at runtime and injects
 * the equivalent layout-update fragment.
 *
 * Hook point: an `after` plugin on
 * {@see ProcessorInterface::load()}. `load()` populates the merge's update
 * buffer; appending another raw XML update via `addUpdate()` after load means
 * it is merged into the layout DOM when the layout is subsequently built
 * (Builder::loadStructure -> getUpdate()->asSimplexml()). This is the standard
 * interception point for dynamic layout-update injection.
 */
class InjectFormLayout
{
    /** @var string FQCN of the Nebula form block, mirrors the legacy layout XMLs */
    private const FORM_BLOCK_CLASS = \Qoliber\NebulaForm\Block\Form::class;

    /**
     * Track which (processor instance + form id) tuples have already been
     * injected. Magento's layout pipeline can call ProcessorInterface::load()
     * more than once per request (cache miss + regenerate, sub-page renders,
     * etc.); without this guard each call would append another copy of our
     * update XML and the Nebula form block would render N times. Two `<form>`
     * elements share the same x-ref + the same hidden input names, so every
     * scalar POST value duplicates and serialised payloads (in_role_user,
     * resource[]) get clobbered by whichever form Alpine resolves last.
     *
     * @var \WeakMap<ProcessorInterface, array<string, true>>|null
     */
    private ?\WeakMap $injectedPerProcessor = null;

    public function __construct(
        private readonly \Magento\Framework\App\Request\Http $request,
        private readonly RouteFormMap $routeFormMap
    ) {
    }

    /**
     * @param \Magento\Framework\View\Layout\ProcessorInterface $subject
     * @param \Magento\Framework\View\Layout\ProcessorInterface $result
     * @return \Magento\Framework\View\Layout\ProcessorInterface
     */
    public function afterLoad(
        ProcessorInterface $subject,
        ProcessorInterface $result
    ): ProcessorInterface {
        $match = $this->routeFormMap->find($this->request->getFullActionName());
        if ($match === null) {
            return $result;
        }

        if ($this->alreadyInjected($result, $match['form_id'])) {
            return $result;
        }

        $subject->addUpdate(
            $this->buildUpdateXml($match['form_id'], $match['replaces'])
        );

        $this->markInjected($result, $match['form_id']);

        return $result;
    }

    private function alreadyInjected(ProcessorInterface $processor, string $formId): bool
    {
        $map = $this->getInjectedMap();
        return isset($map[$processor]) && isset($map[$processor][$formId]);
    }

    private function markInjected(ProcessorInterface $processor, string $formId): void
    {
        $map = $this->getInjectedMap();
        $existing = $map[$processor] ?? [];
        $existing[$formId] = true;
        $map[$processor] = $existing;
    }

    private function getInjectedMap(): \WeakMap
    {
        return $this->injectedPerProcessor ??= new \WeakMap();
    }

    /**
     * Build the layout-update fragment. Produces the same structure the legacy
     * per-page XML files did:
     *  - one `<referenceBlock name="{block}" remove="true"/>` per `replaces`
     *    entry (a legacy form XML may remove several blocks);
     *  - `<referenceContainer name="page.main.actions" remove="true"/>` —
     *    form-shell.phtml provides its own sticky action bar, so the standard
     *    nebula-page-actions toolbar is always redundant on NebulaForm pages;
     *  - `<referenceContainer name="content">` with the Nebula form block.
     *
     * `replaces` values use underscore (`cms_block_form`), dot
     * (`adminhtml.user.edit.tabs`) and dash (`tax-rate-form`) conventions —
     * `referenceBlock` handles all of them, so each value is passed through
     * verbatim (escaped only).
     *
     * @param string $formId
     * @param array<int, string> $replaces
     * @return string
     */
    private function buildUpdateXml(string $formId, array $replaces): string
    {
        $formIdAttr = $this->escapeAttr($formId);

        $xml = '<referenceContainer name="page.main.actions" remove="true"/>';

        foreach ($replaces as $block) {
            $xml .= '<referenceBlock name="' . $this->escapeAttr($block) . '" remove="true"/>';
        }

        $xml .= '<referenceContainer name="content">'
            . '<block class="' . $this->escapeAttr(self::FORM_BLOCK_CLASS) . '"'
            . ' name="nebula.' . $formIdAttr . '">'
            . '<arguments>'
            . '<argument name="form_id" xsi:type="string">' . $this->escapeText($formId) . '</argument>'
            . '</arguments>'
            . '</block>'
            . '</referenceContainer>';

        return $xml;
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function escapeText(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_XML1, 'UTF-8');
    }
}
