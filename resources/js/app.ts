import { bootSite } from './site/boot';

type Globals = {
    MP_CONFIG_URL?: string; MP_CONFIG?: unknown; MP_I18N?: unknown; MP_CONFIG_FAILED?: boolean; mpCalcReady?: Promise<void>;
};
const w = window as unknown as Globals;
const ready = new Promise<void>((resolve) => document.addEventListener('DOMContentLoaded', () => resolve()));

/**
 * The calculator's constants and the texts its scripts say come as one JSON the browser keeps for a day
 * (ConfigController::calc, App\Support\CalculatorTexts, docs/AN.md §12.4) instead of 24 kB inline in every page.
 * The calculator chunk reads them the moment it loads, so it is imported only once they are here (the HTML preloads
 * the chunk, so both travel at once). A failed request is tried once more; after that the page says so and the
 * calculator boots without them (the upload still works, the tools without prices do too). Pages without the address
 * (the tool pages with their own texts, the plain pages) import the chunk at once, as before.
 */
async function loadCalcConfig(): Promise<void> {
    const url = w.MP_CONFIG_URL;
    if (!url || w.MP_CONFIG || typeof fetch !== 'function') return;
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const r = await fetch(url, { credentials: 'same-origin' });
            if (!r.ok) throw new Error(String(r.status));
            const data = (await r.json()) as { config: unknown; i18n: unknown };
            w.MP_CONFIG = data.config; w.MP_I18N = data.i18n;
            return;
        } catch {
            await new Promise((resolve) => setTimeout(resolve, 800));
        }
    }
    w.MP_CONFIG_FAILED = true;
    void ready.then(() => document.getElementById('mp-config-error')?.classList.remove('hidden'));
}

// The calculator and the tools (three.js, most of the code) come in a chunk of their own: the small entry lets the
// first screen paint without them (docs/AH.md §11, Core Web Vitals). The chunk is booted when the document is ready;
// mpCalcReady resolves when the tools have booted (scripts/check_bundle.mjs).
const calc = loadCalcConfig().then(() => import('./calc/boot'));
w.mpCalcReady = Promise.all([calc, ready]).then(([m]) => m.bootCalc());

document.addEventListener('DOMContentLoaded', () => { bootSite(); });
