/**
 * Lightweight lightbox for vendor Image element previews.
 *
 * Magento\Framework\Data\Form\Element\Image emits
 *   <a previewlinkid="…" href="<media-url>"><img class="small-image-preview" …></a>
 * and a vanilla onclick listener that calls imagePreview() — a function defined
 * in mage/adminhtml/browser that doesn't load under nebula. Click did nothing.
 *
 * Build a single reusable modal in the body, hook every <a previewlinkid> to
 * open it with the linked image, dismiss via X / backdrop / ESC. No Alpine
 * dependency — pure DOM so it boots before Alpine and works on any page that
 * happens to render a vendor Image element.
 */

const MODAL_ID = 'nebula-image-preview-modal';

function ensureModal(): HTMLElement {
    let modal = document.getElementById(MODAL_ID);
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = MODAL_ID;
    modal.style.cssText = [
        'position:fixed', 'inset:0', 'z-index:9999', 'display:none',
        'align-items:center', 'justify-content:center',
        'background:rgba(0,0,0,0.75)', 'backdrop-filter:blur(4px)',
        'padding:2rem',
    ].join(';');

    const panel = document.createElement('div');
    panel.style.cssText = [
        'position:relative', 'max-width:min(90vw,1200px)', 'max-height:90vh',
        'display:flex', 'align-items:center', 'justify-content:center',
    ].join(';');

    const img = document.createElement('img');
    img.id = `${MODAL_ID}-img`;
    img.alt = '';
    img.style.cssText = [
        'max-width:100%', 'max-height:90vh',
        'border-radius:0.75rem', 'box-shadow:0 25px 50px -12px rgba(0,0,0,0.5)',
        'display:block',
    ].join(';');

    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close preview');
    close.innerHTML = '&times;';
    close.style.cssText = [
        'position:absolute', 'top:0.5rem', 'right:0.5rem',
        'width:2rem', 'height:2rem', 'border-radius:9999px',
        'background:rgba(0,0,0,0.55)', 'color:#fff',
        'border:0', 'font-size:1.5rem', 'line-height:1', 'cursor:pointer',
        'display:flex', 'align-items:center', 'justify-content:center',
        'transition:background 0.15s ease',
    ].join(';');
    close.addEventListener('mouseenter', () => { close.style.background = 'rgba(0,0,0,0.8)'; });
    close.addEventListener('mouseleave', () => { close.style.background = 'rgba(0,0,0,0.55)'; });
    close.addEventListener('click', () => hideModal());

    panel.appendChild(close);
    panel.appendChild(img);
    modal.appendChild(panel);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal();
    });

    document.body.appendChild(modal);
    return modal;
}

function hideModal(): void {
    const modal = document.getElementById(MODAL_ID);
    if (modal) modal.style.display = 'none';
}

function showModal(src: string): void {
    const modal = ensureModal();
    const img = document.getElementById(`${MODAL_ID}-img`) as HTMLImageElement | null;
    if (img) img.src = src;
    modal.style.display = 'flex';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hideModal();
});

function wireLink(link: HTMLAnchorElement): void {
    if (link.dataset.nebulaImagePreviewBound === '1') return;
    link.dataset.nebulaImagePreviewBound = '1';
    // Clear vendor's onclick — set inline by SecureHtmlRenderer to call the
    // mage/adminhtml/browser global imagePreview() that doesn't load under
    // nebula. Throwing ReferenceError every click is noisy and pointless.
    link.onclick = null;
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const img = link.querySelector('img');
        const src = link.getAttribute('href') ?? img?.getAttribute('src') ?? '';
        if (src) showModal(src);
    });
}

export function bootImagePreview(): void {
    // Belt-and-braces stub for any vendor onclick we don't catch in time
    // (e.g. dynamically-rendered fields). Returning false matches what
    // vendor's stringified handler did after calling imagePreview.
    if (typeof (window as unknown as { imagePreview?: unknown }).imagePreview !== 'function') {
        (window as unknown as { imagePreview: () => boolean }).imagePreview = () => false;
    }
    const links = document.querySelectorAll<HTMLAnchorElement>('a[previewlinkid]');
    links.forEach(wireLink);
}
