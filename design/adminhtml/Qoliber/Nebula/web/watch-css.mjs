#!/usr/bin/env node
/**
 * Tailwind 4 --watch only follows the entry file and misses @import'd partials.
 * This wrapper fs.watches css/source/** and runs tailwindcss as a subprocess
 * on any change, debounced 50ms.
 */

import { spawn } from 'node:child_process';
import { watch, existsSync } from 'node:fs';
import { resolve } from 'node:path';

const SOURCE_DIR = resolve('css/source');
// Skip npx — direct binary is ~1s faster per spawn. Fall back to npx if the
// node_modules binary isn't present (CI / fresh clone before npm install).
const BIN = resolve('node_modules/.bin/tailwindcss');
const CMD = existsSync(BIN) ? BIN : 'npx';
const ARGS = existsSync(BIN)
    ? ['-i', 'css/source/app.css', '-o', 'css/dist/app.css', '--minify']
    : ['tailwindcss', '-i', 'css/source/app.css', '-o', 'css/dist/app.css', '--minify'];

let pending = null;
let running = false;

function rebuild() {
    if (running) {
        // queue another rebuild after the current one finishes
        pending = setTimeout(rebuild, 50);
        return;
    }
    running = true;
    const start = Date.now();
    const proc = spawn(CMD, ARGS, { stdio: ['ignore', 'inherit', 'inherit'] });
    proc.on('close', (code) => {
        running = false;
        const ms = Date.now() - start;
        if (code === 0) {
            console.log(`[css] rebuilt in ${ms}ms`);
        } else {
            console.error(`[css] tailwindcss exited with ${code}`);
        }
    });
}

function schedule() {
    if (pending) clearTimeout(pending);
    pending = setTimeout(() => {
        pending = null;
        rebuild();
    }, 50);
}

console.log(`[css] watching ${SOURCE_DIR}`);
rebuild(); // initial build

watch(SOURCE_DIR, { recursive: true }, (_event, filename) => {
    if (!filename || !filename.endsWith('.css')) return;
    schedule();
});

process.on('SIGINT', () => {
    console.log('\n[css] stopped');
    process.exit(0);
});
