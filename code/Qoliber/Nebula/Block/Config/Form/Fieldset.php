<?php

declare(strict_types=1);

namespace Qoliber\Nebula\Block\Config\Form;

use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Nebula config fieldset renderer.
 * Alpine.js collapsible cards with pin support.
 */
class Fieldset extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    private function getPinnedSections(): array
    {
        $user = $this->_authSession->getUser();
        $extra = $user ? ($user->getExtra() ?: []) : [];

        return $extra['nebulaPinnedSections'] ?? [];
    }

    private function isPinned(AbstractElement $element): bool
    {
        return in_array($element->getId(), $this->getPinnedSections());
    }

    protected function _getHeaderHtml($element): string
    {
        $htmlId = $element->getHtmlId();
        $legend = $element->getLegend();
        $isPinned = $this->isPinned($element);
        $sectionId = $element->getId();

        // Pinned sections are always open; otherwise closed by default
        $isOpen = $isPinned ? true : (bool) $this->_isCollapseState($element);

        $group = $element->getGroup();
        $cssClass = isset($group['fieldset_css']) ? $group['fieldset_css'] : '';
        $pinUrl = $this->getUrl('nebula/config/pin');

        // id="row_<htmlId>" on the outer wrapper so config-depends.ts can
        // toggle the entire group when it carries a <depends> in system.xml
        // (e.g. Security.txt contact_information / other_information depend
        // on general/enabled).
        $rowAttr = ' id="row_' . $htmlId . '"';
        $html = '';
        if ($element->getIsNested()) {
            $html .= '<div' . $rowAttr . ' class="nebula-config-group-nested ' . $cssClass . '">';
        } else {
            $html .= '<div' . $rowAttr . ' class="nebula-config-group ' . $cssClass . '">';
        }

        $html .= '<div x-data="nebulaFieldset_' . preg_replace('/[^a-zA-Z0-9]/', '_', $htmlId) . '()" '
            . 'class="nebula-fieldset-card" '
            . ':class="open ? \'nebula-fieldset-open\' : \'\'">';

        // Header with toggle + pin
        $html .= '<div class="nebula-fieldset-header">';

        $html .= '<button type="button" @click="toggle()" '
            . 'class="nebula-fieldset-toggle">';
        // Chevron icon — rotates when open
        $html .= '<svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200" '
            . ':class="open ? \'rotate-180\' : \'\'" '
            . 'xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>'
            . '</svg>';
        $html .= '<span class="nebula-fieldset-title">' . $legend . '</span>';
        $html .= '</button>';

        // Pin button
        $html .= '<button type="button" @click="togglePin()" '
            . 'class="nebula-fieldset-pin" '
            . ':class="pinned ? \'nebula-fieldset-pinned\' : \'\'" '
            . ':title="pinned ? \'Unpin section\' : \'Pin section\'">'
            . '<svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" '
            . ':fill="pinned ? \'currentColor\' : \'none\'" '
            . 'viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">'
            . '<path stroke-linecap="round" stroke-linejoin="round" '
            . 'd="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" '
            . 'd="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>'
            . '</svg>'
            . '</button>';

        $html .= '</div>';

        // Hidden state input
        $html .= '<input id="' . $htmlId . '-state" name="config_state[' . $sectionId . ']" '
            . 'type="hidden" :value="open ? 1 : 0"/>';

        // Body
        $html .= '<div x-show="open" x-collapse x-cloak class="nebula-fieldset-body">';

        if ($element->getComment()) {
            $html .= '<p class="nebula-fieldset-comment">' . $element->getComment() . '</p>';
        }

        // Alpine component
        $html .= '<script>'
            . 'function nebulaFieldset_' . preg_replace('/[^a-zA-Z0-9]/', '_', $htmlId) . '() {'
            . '  return {'
            . '    open: ' . ($isOpen ? 'true' : 'false') . ','
            . '    pinned: ' . ($isPinned ? 'true' : 'false') . ','
            . '    toggle() { this.open = !this.open; },'
            . '    async togglePin() {'
            . '      try {'
            . '        const resp = await fetch("' . $pinUrl . '", {'
            . '          method: "POST",'
            . '          headers: {"Content-Type": "application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest"},'
            . '          body: new URLSearchParams({section: "' . addslashes($sectionId) . '", form_key: document.querySelector("[name=form_key]").value})'
            . '        });'
            . '        const data = await resp.json();'
            . '        if (data.success) {'
            . '          this.pinned = data.pinned;'
            . '          if (data.pinned) this.open = true;'
            . '        }'
            . '      } catch(e) { console.error("Pin failed:", e); }'
            . '    }'
            . '  }'
            . '}'
            . '</script>';

        return $html;
    }

    protected function _getFooterHtml($element): string
    {
        $html = '</div>'; // nebula-fieldset-body
        $html .= '</div>'; // nebula-fieldset-card
        $html .= '</div>'; // nebula-config-group

        return $html;
    }

    protected function _getExtraJs($element): string
    {
        return '';
    }

    protected function _getChildrenElementsHtml($element): string
    {
        $elements = '';
        foreach ($element->getElements() as $field) {
            $renderer = $field->getRenderer();
            $isVendorFieldRenderer = $renderer !== null
                && !($renderer instanceof Field)
                && !($renderer instanceof self);

            if (!$isVendorFieldRenderer) {
                // Nested Fieldset (renderer is self) or already-nebula Field — render straight through.
                $elements .= $field->toHtml();
                continue;
            }

            // Run vendor renderer for its gate/state side-effects + capture custom control HTML,
            // then discard its <td> wrapper and re-emit through nebula chrome.
            $vendorHtml = (string) $renderer->render($field);
            if (trim($vendorHtml) === '') {
                continue;
            }

            $controlHtml = $this->extractValueCellInner($vendorHtml);
            if ($controlHtml === null) {
                // Renderer produced complete HTML on its own (FieldArray plugin,
                // custom block emitting full markup, etc.) — no <td class="value">
                // to extract. Re-rendering through nebula Field would crash on
                // array-valued elements via $element->getElementHtml(). Emit verbatim.
                $elements .= $vendorHtml;
                continue;
            }

            /** @var Field $nebulaRenderer */
            $nebulaRenderer = $this->getLayout()->createBlock(Field::class);
            $nebulaRenderer->setElementHtml($controlHtml);
            $elements .= $nebulaRenderer->render($field);
        }

        return $elements;
    }

    private function extractValueCellInner(string $html): ?string
    {
        if (!str_contains($html, 'class="value"') && !str_contains($html, "class='value'")) {
            return null;
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $node = $xpath->query('//td[contains(concat(" ", normalize-space(@class), " "), " value ")]')->item(0);
        if ($node === null) {
            return null;
        }

        // Strip vendor's <p class="note">…</p> — nebula chrome emits its own
        // .nebula-config-note from $element->getComment(), and keeping vendor's
        // copy here would double-render every commented field.
        foreach ($xpath->query('.//p[contains(concat(" ", normalize-space(@class), " "), " note ")]', $node) as $noteNode) {
            $noteNode->parentNode?->removeChild($noteNode);
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $doc->saveHTML($child);
        }

        return trim($inner);
    }
}
