/**
 * Product-edit accessibility shim — adds `aria-label` and `title` to
 * buttons without any visible text on the catalog product-edit page.
 *
 * Extracted from the inline `<script>` in
 * `NebulaCatalog/view/adminhtml/templates/scripts.phtml`.
 */

function applyButtonAccessibility(): void {
    if (!document.body.classList.contains('catalog-product-edit')) {
        return;
    }

    document.querySelectorAll<HTMLButtonElement>('button').forEach((button) => {
        if (button.hasAttribute('aria-label')) return;

        const visibleText = (button.textContent ?? '').replace(/\s+/g, ' ').trim();
        if (visibleText.length > 0) return;

        const title = (button.getAttribute('title') ?? '').trim();
        const dataLabel = (button.getAttribute('data-label') ?? '').trim();
        const name = (button.getAttribute('name') ?? '').trim();
        const id = (button.getAttribute('id') ?? '').trim();
        const fallback = title || dataLabel || name || id || 'Action button';

        button.setAttribute('aria-label', fallback);
        if (!button.hasAttribute('title')) {
            button.setAttribute('title', fallback);
        }
    });
}

document.addEventListener('DOMContentLoaded', applyButtonAccessibility);
