// @ts-check
import { build, context } from 'esbuild';
import { rmSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const outDir = join(__dirname, 'js', 'dist');

/** @type {Array<{entry: string, out: string}>} */
const bundles = [
    { entry: 'ts/nebula-core.ts', out: 'nebula-core.js' },
    { entry: 'ts/nebula-grid.ts', out: 'nebula-grid.js' },
    { entry: 'ts/nebula-form.ts', out: 'nebula-form.js' },
    { entry: 'ts/pages/media.ts', out: 'nebula-media.js' },
    { entry: 'ts/pages/quill.ts', out: 'nebula-quill.js' },
    { entry: 'ts/pages/bundle-options.ts', out: 'nebula-bundle-options.js' },
    { entry: 'ts/pages/custom-options.ts', out: 'nebula-custom-options.js' },
    { entry: 'ts/pages/customer-addresses.ts', out: 'nebula-customer-addresses.js' },
    { entry: 'ts/pages/pagebuilder.ts', out: 'nebula-pagebuilder.js' },
    { entry: 'ts/pages/catalog-product.ts', out: 'nebula-catalog-product.js' },
    // Standalone: for pages that load rule-editor without nebula-core (e.g., Catalog > Price Rules).
    { entry: 'ts/rule-editor.ts', out: 'nebula-rule-editor.js' },
    // Vendor globals — dashboard/drag-drop phtml still reads window.Chart /
    // window.Sortable. npm-sourced, bundled, no more unversioned min.js files.
    { entry: 'ts/vendor-chart.ts', out: 'nebula-vendor-chart.js' },
    { entry: 'ts/vendor-sortable.ts', out: 'nebula-vendor-sortable.js' },
];

const watch = process.argv.includes('--watch');
const prod = !watch;
// Source maps default to ON for development convenience but add ~1.6MB to
// the released artifact set. Production CI should run `SOURCEMAPS=false npm
// run build` (or pass --no-sourcemaps as the second positional flag) to
// emit only the .js files.
const sourcemap = (process.env.SOURCEMAPS ?? 'true').toLowerCase() !== 'false'
    && !process.argv.includes('--no-sourcemaps');

// Clean dist on fresh build.
try { rmSync(outDir, { recursive: true, force: true }); } catch { /* ignore */ }
mkdirSync(outDir, { recursive: true });

/** @type {import('esbuild').BuildOptions} */
const sharedOptions = {
    bundle: true,
    format: 'iife',
    target: ['es2022'],
    platform: 'browser',
    minify: prod,
    sourcemap,
    logLevel: 'info',
    legalComments: 'none',
    charset: 'utf8',
    treeShaking: true,
};

async function run() {
    if (watch) {
        const ctxs = await Promise.all(
            bundles.map((b) =>
                context({
                    ...sharedOptions,
                    entryPoints: [join(__dirname, b.entry)],
                    outfile: join(outDir, b.out),
                }),
            ),
        );
        await Promise.all(ctxs.map((c) => c.watch()));
        // eslint-disable-next-line no-console
        console.log('[nebula] watching…');
    } else {
        await Promise.all(
            bundles.map((b) =>
                build({
                    ...sharedOptions,
                    entryPoints: [join(__dirname, b.entry)],
                    outfile: join(outDir, b.out),
                }),
            ),
        );
        // eslint-disable-next-line no-console
        console.log('[nebula] built ' + bundles.length + ' bundles into js/dist/');
    }
}

run().catch((err) => {
    // eslint-disable-next-line no-console
    console.error(err);
    process.exit(1);
});
