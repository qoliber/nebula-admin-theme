/**
 * Exposes Sortable as a browser global (`window.Sortable`) the same way the
 * old vendored `sortable.min.js` did.
 */

import Sortable from 'sortablejs';

declare global {
    interface Window {
        Sortable: typeof Sortable;
    }
}

window.Sortable = Sortable;
