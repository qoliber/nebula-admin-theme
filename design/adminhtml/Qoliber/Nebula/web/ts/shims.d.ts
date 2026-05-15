/**
 * Module shims for dependencies without bundled TypeScript declarations.
 */

declare module '@alpinejs/collapse' {
    import type { PluginCallback } from 'alpinejs';
    const plugin: PluginCallback;
    export default plugin;
}

declare module '*.css';
