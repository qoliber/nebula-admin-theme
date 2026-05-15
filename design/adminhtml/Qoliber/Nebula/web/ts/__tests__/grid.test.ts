import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createGrid } from '../grid';

describe('createGrid', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('parses the pending filters from the config JSON', () => {
        const g = createGrid({ filters: '{"status":"enabled","price":"10"}', formKey: 'fk' });
        expect(g.pendingFilters).toEqual({ status: 'enabled', price: '10' });
    });

    it('defaults pending filters to empty object on missing input', () => {
        const g = createGrid({ formKey: 'fk' });
        expect(g.pendingFilters).toEqual({});
    });

    it('falls back to empty filters on malformed JSON', () => {
        const g = createGrid({ filters: '{bad', formKey: 'fk' });
        expect(g.pendingFilters).toEqual({});
    });

    it('toggleSelectAll(true) reads every checkbox value in the tbody', () => {
        const root = document.createElement('div');
        root.innerHTML =
            '<table><tbody>' +
            '<tr><td><input type="checkbox" value="1"></td></tr>' +
            '<tr><td><input type="checkbox" value="2"></td></tr>' +
            '<tr><td><input type="checkbox" value="3"></td></tr>' +
            '</tbody></table>';
        const g = createGrid({ formKey: 'fk' });
        g.$root = root;

        g.toggleSelectAll(true);
        expect(g.selected).toEqual(['1', '2', '3']);

        g.toggleSelectAll(false);
        expect(g.selected).toEqual([]);
    });

    it('toggleSelectAll without a $root degrades to empty selection', () => {
        const g = createGrid({ formKey: 'fk' });
        g.toggleSelectAll(true);
        expect(g.selected).toEqual([]);
    });

    it('confirmMassAction records url+label when selection is non-empty', () => {
        const g = createGrid({ formKey: 'fk' });
        g.selected = ['1'];
        g.confirmMassAction('/admin/delete', 'Delete');
        expect(g.confirmAction).toEqual({ url: '/admin/delete', label: 'Delete' });
    });

    it('confirmMassAction no-ops on empty selection', () => {
        const g = createGrid({ formKey: 'fk' });
        g.confirmMassAction('/admin/delete', 'Delete');
        expect(g.confirmAction).toBeNull();
    });

    it('executeMassAction submits a form with form_key + ids[] hidden inputs', () => {
        const g = createGrid({ formKey: 'secret-key' });
        g.selected = ['11', '22'];
        g.confirmAction = { url: '/admin/massDelete', label: 'Delete' };

        const submitSpy = vi
            .spyOn(HTMLFormElement.prototype, 'submit')
            .mockImplementation(() => undefined);

        g.executeMassAction();

        const form = document.body.querySelector('form');
        expect(form).not.toBeNull();
        expect(form!.method.toLowerCase()).toBe('post');
        expect(form!.action).toContain('/admin/massDelete');

        const formKey = form!.querySelector<HTMLInputElement>('input[name="form_key"]');
        expect(formKey?.value).toBe('secret-key');

        const ids = Array.from(
            form!.querySelectorAll<HTMLInputElement>('input[name="ids[]"]'),
        ).map((i) => i.value);
        expect(ids).toEqual(['11', '22']);

        expect(submitSpy).toHaveBeenCalledOnce();
        expect(g.confirmAction).toBeNull();
        submitSpy.mockRestore();
    });

    it('executeMassAction is a no-op when no confirmAction is set', () => {
        const g = createGrid({ formKey: 'fk' });
        g.executeMassAction();
        expect(document.body.querySelector('form')).toBeNull();
    });

    it('applyAllFilters rewrites URL search params', () => {
        const originalHref = window.location.href;
        // happy-dom allows assigning `location.href` to swap URL.
        window.history.pushState({}, '', '/admin/grid?filters[existing]=x&page=4');

        const originalLocation = window.location;
        const hrefCapture: string[] = [];
        Object.defineProperty(window, 'location', {
            writable: true,
            value: new Proxy(originalLocation, {
                set(t, p, value) {
                    if (p === 'href') {
                        hrefCapture.push(String(value));
                        return true;
                    }
                    (t as unknown as Record<string | symbol, unknown>)[p as string] = value;
                    return true;
                },
                get(t, p) {
                    return (t as unknown as Record<string | symbol, unknown>)[p as string];
                },
            }),
        });

        const g = createGrid({ filters: '{"status":"enabled"}', formKey: 'fk' });
        g.applyAllFilters();

        expect(hrefCapture.length).toBe(1);
        const newUrl = new URL(hrefCapture[0] ?? '', 'http://localhost');
        expect(newUrl.searchParams.get('filters[status]')).toBe('enabled');
        expect(newUrl.searchParams.has('filters[existing]')).toBe(false);
        expect(newUrl.searchParams.get('page')).toBe('1');

        // Restore.
        Object.defineProperty(window, 'location', { writable: true, value: originalLocation });
        window.history.replaceState({}, '', originalHref);
    });
});
