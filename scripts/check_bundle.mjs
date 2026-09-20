// Smoke check for the built front-end bundle: it must load without throwing on a page that has none of the
// calculator globals (tools, inquiry, quote pages). Run after `npm run build`:  node scripts/check_bundle.mjs
import { readFileSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const manifest = JSON.parse(readFileSync(join(root, 'public/build/manifest.json'), 'utf8'));
const entry = manifest['resources/js/app.ts']?.file;
if (!entry) { console.error('entry not found in manifest'); process.exit(1); }

const noop = () => {};
const el = () => ({ addEventListener: noop, classList: { add: noop, remove: noop, toggle: noop, contains: () => false }, querySelector: () => null, querySelectorAll: () => [], style: {}, dataset: {}, appendChild: noop, insertAdjacentHTML: noop });
let domReady = null;
globalThis.window = globalThis;
globalThis.self = globalThis;
globalThis.document = {
    documentElement: { lang: 'cs' }, body: el(), visibilityState: 'visible',
    getElementById: () => null, querySelector: () => null, querySelectorAll: () => [],
    createElement: el, createElementNS: el,
    addEventListener: (name, fn) => { if (name === 'DOMContentLoaded') domReady = fn; },
};
globalThis.location = { search: '', href: 'http://localhost/' };
globalThis.history = { replaceState: noop };
try { Object.defineProperty(globalThis, 'navigator', { value: { userAgent: 'node' }, configurable: true }); } catch { /* read-only in some node versions */ }
globalThis.requestAnimationFrame = noop;
globalThis.ResizeObserver = class { observe() {} };

try {
    await import(pathToFileURL(join(root, 'public/build', entry)).href);
    if (domReady) domReady(); // boot functions must tolerate pages without their elements
    console.log('bundle ok:', entry);
} catch (e) {
    console.error('BUNDLE CRASHES ON A PLAIN PAGE:', e?.message ?? e);
    process.exit(1);
}
