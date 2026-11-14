#!/usr/bin/env node
/**
 * Every example under the home page's field ("Co chcete vyrobit?") walked the way a visitor walks it, in a real
 * headless Chrome against a local server: the chip is clicked, "Vytvořit" is pressed (twice: the first press asks to
 * make the example one's own), the page that opens is waited for, a picture is taken and the form is read. What the
 * form holds is compared with what the hint's sentence names (`php artisan matplace:intake-hints --json`: the words
 * read by App\Domain\Intake\IntakePrefill and the values the tool's form must open with). A hint that ends on a
 * tool's default sample, on another place or without the colours it names is printed as NO and the script exits 1.
 * docs/AN.md §25. No packages: Chrome's DevTools protocol, the way of scripts/shots_home_direct.mjs.
 *
 * The server must let the walk through: its own database with the `intakes` table, and the limit of wishes lifted
 * (INTAKE_PER_HOUR=100000 INTAKE_PER_DAY=100000 in its environment). Without ANTHROPIC_API_KEY (ENGINE_ASSISTANT=claude)
 * the keyword router decides, which is the weakest reader: what passes here passes with the model too, as long as the
 * model chooses the same tool. Nothing paid is called by this script.
 *
 *   node scripts/hints_walk.mjs --base=http://127.0.0.1:8041 --out=<dir> [--langs=cs,en,es] [--only=steady.3,mikulas.2]
 *        [--width=1280] [--hints=<file.json>] [--php=php] [--text="3D mapa Prahy, 15 cm" --tool=map] [--then=calc]
 *
 * `--hints` is the list as the artisan command prints it (default: the command is run here, in the repository's root).
 * `--text` walks one wish of your own instead of the hints (no expectations, just the picture and the form).
 * `--then=calc` presses the tool's main button afterwards and takes a second picture of the calculation the design
 * opens in (`<name>-calc.webp`): the colours of the design have to be there too.
 */
import { spawn, execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, rmSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
    return hit ? hit.slice(hit.indexOf('=') + 1) : fallback;
};
const BASE = String(arg('base', 'http://127.0.0.1:8041')).replace(/\/$/, '');
const OUT = String(arg('out', 'storage/app/hints-walk'));
const LANGS = String(arg('langs', 'cs')).split(',').filter(Boolean);
const ONLY = arg('only', null);
const WIDTH = Number(arg('width', 1280));
const THEN = arg('then', null);
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
mkdirSync(OUT, { recursive: true });

let hints;
if (arg('text', null)) {
    hints = LANGS.map((locale) => ({ code: 'own.1', locale, label: 'own wish', fill: String(arg('text', '')), tool: arg('tool', null), expect: null }));
} else if (arg('hints', null)) {
    hints = JSON.parse(readFileSync(String(arg('hints')), 'utf8'));
} else {
    hints = JSON.parse(execFileSync(String(arg('php', 'php')), ['artisan', 'matplace:intake-hints', '--json'], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 }));
}
hints = hints.filter((h) => LANGS.includes(h.locale) && (!ONLY || String(ONLY).split(',').includes(h.code)));

async function openChrome(port) {
    const profile = mkdtempSync(join(tmpdir(), 'mp-hints-'));
    const proc = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--enable-unsafe-swiftshader', 'about:blank'], { stdio: 'ignore' });
    let version = null;
    for (let i = 0; i < 60 && !version; i++) {
        await sleep(250);
        try { version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); } catch { /* not up yet */ }
    }
    if (!version) throw new Error('Chrome did not start');
    const ws = new WebSocket(version.webSocketDebuggerUrl);
    await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
    let id = 0; const waiting = new Map();
    ws.onmessage = (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id && waiting.has(msg.id)) { const { res, rej } = waiting.get(msg.id); waiting.delete(msg.id); msg.error ? rej(new Error(msg.error.message)) : res(msg.result); }
    };
    const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const n = ++id; waiting.set(n, { res, rej }); ws.send(JSON.stringify({ id: n, method, params, sessionId })); });
    /** A page of its own browser context: every hint is a new visitor (its own cookies, its own session). */
    const visitor = async () => {
        const { browserContextId } = await send('Target.createBrowserContext');
        const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
        return { cdp: (method, params) => send(method, params, sessionId), leave: async () => { try { await send('Target.disposeBrowserContext', { browserContextId }); } catch { /* gone */ } } };
    };
    const close = async () => { try { await send('Browser.close'); } catch { /* gone */ } ws.close(); proc.kill(); await sleep(400); try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ } };
    return { visitor, close };
}

const evaluate = async (cdp, expression) => (await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result.value;
const until = async (cdp, expression, ms, step = 300) => {
    const end = Date.now() + ms;
    while (Date.now() < end) {
        try { if (await evaluate(cdp, expression)) return true; } catch { /* the page is changing */ }
        await sleep(step);
    }
    return false;
};

/** What the page that opened holds: where it is, the line "the assistant understood", every field of the form, the colours, the picture. */
const READ = `(() => {
    const q = (s) => document.querySelector(s);
    const all = (s) => [...document.querySelectorAll(s)];
    const shown = (el) => !!el && !el.classList.contains('hidden') && el.getClientRects().length > 0;
    const form = {};
    all('[data-param]').forEach((i) => { form[i.dataset.param] = i.value; });
    all('[data-text]').forEach((i) => { form[i.dataset.text] = i.value; });
    all('[data-flag]').forEach((i) => { form[i.dataset.flag] = i.checked; });
    all('[data-choice]').forEach((i) => { if (i.dataset.color !== undefined) form[i.dataset.choice] = i.value; else if (i.checked) form[i.dataset.choice] = i.value; });
    all('input[type=radio]:checked').forEach((i) => { if (i.name && !(i.name in form) && !i.dataset.choice) form[i.name] = i.value; });
    ['figure-size', 'relief-w', 'map-place', 'param-qty', 'param-material'].forEach((id) => { const el = document.getElementById(id); if (el) form['#' + id] = el.value; });
    const name = q('input[name="pedestal_name"]'); if (name) form.pedestal_name = name.value;
    // the colour of every part as its row shows it (the swatch carries the colour's value), and the picture the form holds
    const colors = {};
    all('#tool-parts [data-part], #map-parts [data-part]').forEach((row) => { const sw = row.querySelector('.tool-swatch'); if (sw) colors[row.dataset.part] = (sw.dataset.code || '').toLowerCase(); });
    const art = q('form[data-artwork]');
    const state = { colors, artwork: art ? art.dataset.artwork : null };
    return {
        url: location.pathname + location.search,
        title: (q('#tool-name') || q('h1'))?.textContent?.trim() ?? null,
        line: q('#tool-intake')?.innerText?.trim() ?? null,
        ask: q('[data-intake-ask]')?.innerText?.trim() ?? null,
        card: shown(q('#intake-assist')) ? q('#intake-assist').innerText.trim() : null,
        error: shown(q('#tool-error')) ? q('#tool-error').innerText.trim() : null,
        status: q('#tool-status')?.innerText?.trim() ?? null,
        empty: shown(q('#tool-empty')),
        busy: shown(q('#tool-busy')),
        form,
        state,
        map: shown(q('#map-chosen')) ? q('#map-chosen').innerText.trim() : null,
        dims: q('#param-dims')?.innerText?.trim() ?? null,
        scroll: document.documentElement.scrollWidth > innerWidth,
        errors: window.__errs || [],
    };
})()`;

const plain = (s) => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

/** The hint's expectations against the page: every value the form must hold, the place of a map, the colours of the parts. */
function judge(hint, got) {
    const missing = [];
    const e = hint.expect;
    if (!e) return missing;
    if (e.tool && !got.url.startsWith(e.path)) missing.push(`opened ${got.url} instead of ${e.path}`);
    for (const [key, want] of Object.entries(e.form ?? {})) {
        const have = got.form[key];
        const same = typeof want === 'boolean' ? have === want : typeof want === 'number' ? Math.abs(Number(have) - want) < 0.51 : plain(have) === plain(want);
        if (!same) missing.push(`${key}: ${JSON.stringify(have)} instead of ${JSON.stringify(want)}`);
    }
    for (const [part, want] of Object.entries(e.colors ?? {})) {
        const have = got.state?.colors?.[part] ?? null;
        if (have !== want) missing.push(`colour of ${part}: ${JSON.stringify(have)} instead of ${JSON.stringify(want)}`);
    }
    if (e.artwork !== undefined && (got.state?.artwork ?? '') !== e.artwork) missing.push(`picture: ${JSON.stringify(got.state?.artwork ?? '')} instead of ${JSON.stringify(e.artwork)}`);
    if (e.ask && !got.ask) missing.push('the page does not ask for what is missing');
    if (e.place && !plain(got.map).includes(plain(e.place))) missing.push(`map shows ${JSON.stringify(got.map)} instead of ${e.place}`);
    if (got.error) missing.push(`error on the page: ${got.error}`);
    if (got.errors.length) missing.push(`script errors: ${got.errors.join(' | ')}`);
    if (got.scroll) missing.push('the page scrolls sideways');
    return missing;
}

async function walk(browser, hint) {
    const { cdp, leave } = await browser.visitor();
    const name = `${hint.locale}-${hint.code.replace('.', '-')}`;
    try {
        await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable');
        await cdp('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: WIDTH < 600 ? 844 : 900, deviceScaleFactor: 1, mobile: WIDTH < 600 });
        await cdp('Network.setUserAgentOverride', { userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 HeadlessChrome matplace-review', acceptLanguage: hint.locale });
        await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
        await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
        await cdp('Page.addScriptToEvaluateOnNewDocument', { source: "window.__errs = []; window.addEventListener('error', (e) => window.__errs.push(String(e.message))); window.addEventListener('unhandledrejection', (e) => window.__errs.push('rejection: ' + String(e.reason && e.reason.message || e.reason)));" });
        const home = BASE + (hint.locale === 'cs' ? '/' : `/${hint.locale}`);
        await cdp('Page.navigate', { url: home });
        if (!await until(cdp, "!!document.getElementById('intake-submit') && document.readyState === 'complete'", 20000)) throw new Error('the home page did not load');
        // the chip of this hint; a hint whose season is not open today takes the place of the first chip (the click reads the chip as it is)
        const clicked = await evaluate(cdp, `(() => {
            const h = ${JSON.stringify(hint)};
            const chips = [...document.querySelectorAll('#intake-form [data-fill]')];
            let chip = chips.find((c) => c.dataset.hint === h.code && c.dataset.fill === h.fill);
            const own = !!chip;
            if (!chip) { chip = chips[0]; if (!chip) return 'no chips'; chip.dataset.hint = h.code; chip.dataset.fill = h.fill; chip.textContent = h.label; }
            chip.click();
            return own ? 'chip' : 'chip put in';
        })()`);
        if (clicked === 'no chips') throw new Error('the home page shows no examples');
        await sleep(3400);     // a wish sent sooner than a person can is held as a robot's
        const pressed = "document.getElementById('intake-submit').click()";
        await evaluate(cdp, pressed);
        await sleep(400);
        const nudge = await evaluate(cdp, "document.getElementById('intake-hint')?.textContent ?? ''");
        await evaluate(cdp, pressed);
        const left = await until(cdp, `location.pathname.includes('/tools/') || location.search.includes('design=') || (document.getElementById('intake-assist') && !document.getElementById('intake-assist').classList.contains('hidden'))`, 30000);
        if (left && await evaluate(cdp, "location.pathname.includes('/tools/')")) {
            await until(cdp, "document.readyState === 'complete' && !!document.getElementById('tool-page')", 20000);
            await sleep(2500);
            // a map looks its place up, draws the area and then makes the model in the queue: waited for until it stands, or the page says why not
            if (await evaluate(cdp, "!!document.getElementById('map-form') && document.getElementById('map-place').value.trim() !== ''")) {
                await until(cdp, "(() => { const shown = (id) => { const el = document.getElementById(id); return !!el && !el.classList.contains('hidden') && el.textContent.trim() !== ''; }; return shown('map-facts') || shown('tool-error') || (shown('map-place-state') && !shown('map-chosen')); })()", 300000, 1000);
            }
            // the preview: drawn (nothing busy) or the page says what it waits for
            await until(cdp, "(() => { const b = document.getElementById('tool-busy'); return !b || b.classList.contains('hidden') || b.getClientRects().length === 0; })()", 60000, 500);
            await sleep(2500);
        } else {
            await sleep(1500);
        }
        const got = await evaluate(cdp, READ);
        const shot = await cdp('Page.captureScreenshot', { format: 'webp', quality: 70 });
        writeFileSync(join(OUT, `${name}.webp`), Buffer.from(shot.data, 'base64'));
        // --then=calc: the design goes on to the calculation (the main button), where the order is made: do its colours arrive?
        let calc = null;
        if (THEN === 'calc' && await evaluate(cdp, "(() => { const b = document.getElementById('tool-go'); return !!b && !b.disabled && location.pathname.includes('/tools/'); })()")) {
            await evaluate(cdp, "document.getElementById('tool-go').click()");
            if (await until(cdp, "location.search.includes('open=')", 90000, 500)) {
                await until(cdp, "document.readyState === 'complete'", 20000);
                await sleep(12000);
                calc = await evaluate(cdp, `(() => ({
                    url: location.pathname + location.search,
                    colors: [...document.querySelectorAll('[data-part-color], #part-colors [data-part], #calc-parts [data-part], [data-color-row]')].map((el) => el.innerText.replace(/\\s+/g, ' ').trim()).slice(0, 12),
                    text: (document.getElementById('calc-colors') || document.getElementById('result') || document.body).innerText.replace(/\\s+/g, ' ').slice(0, 900),
                }))()`);
                const shot2 = await cdp('Page.captureScreenshot', { format: 'webp', quality: 70 });
                writeFileSync(join(OUT, `${name}-calc.webp`), Buffer.from(shot2.data, 'base64'));
            }
        }
        return { ...hint, shot: `${name}.webp`, clicked, nudge, got, calc, missing: judge(hint, got) };
    } catch (e) {
        return { ...hint, shot: null, got: null, missing: [`the walk failed: ${e.message}`] };
    } finally {
        await leave();
    }
}

const browser = await openChrome(9400 + Math.floor(Math.random() * 400));
const results = [];
try {
    for (const hint of hints) {
        const r = await walk(browser, hint);
        results.push(r);
        const ok = r.missing.length === 0;
        console.log(`${ok ? (hint.expect ? 'OK ' : '-- ') : 'NO '} ${hint.locale} ${hint.code.padEnd(15)} ${String(r.got?.url ?? '').padEnd(46)} ${hint.fill}`);
        if (r.got?.line) console.log(`       ${r.got.line.replace(/\s+/g, ' ')}`);
        if (r.got?.ask) console.log(`       asks: ${r.got.ask.replace(/\s+/g, ' ')}`);
        if (r.got?.card) console.log(`       card: ${r.got.card.replace(/\s+/g, ' ').slice(0, 200)}`);
        r.missing.forEach((m) => console.log(`       ! ${m}`));
    }
} finally {
    await browser.close();
}
writeFileSync(join(OUT, 'walk.json'), JSON.stringify(results, null, 2));
const bad = results.filter((r) => r.missing.length);
console.log(`\n${results.length} hints walked, ${results.length - bad.length} match their sentence, ${bad.length} do not. Pictures and walk.json: ${OUT}`);
process.exit(bad.length ? 1 : 0);
