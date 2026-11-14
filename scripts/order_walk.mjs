#!/usr/bin/env node
/**
 * From a tool straight to the order, the price only on the customer's word (docs/AN.md §26), walked the way a visitor
 * walks it in a real headless Chrome against a local server (fake slicer, sync queue):
 *
 *   the tool's page → its main button ("Přejít k tisku") → the order page waiting for the choices (no price, no slice)
 *   → a colour, a number of pieces → "Spočítat cenu" → the price → another number of pieces (the price out of date, the
 *   payment held back) → "Přepočítat cenu" → the new price → "Upravit tvar a rozměry" back to the tool → the button
 *   again → the very same order.
 *
 * A picture of every step and what the page said go to --out; a step that did not end as described is printed as NO
 * and the script exits 1. No packages: Chrome's DevTools protocol, the way of scripts/hints_walk.mjs.
 *
 *   node scripts/order_walk.mjs --base=http://127.0.0.1:8046 --out=<dir> [--tool=coaster] [--width=1280|390]
 *        [--login=email:password] [--expect=order|calc] [--name=guest-1280]
 *
 * `--expect=calc`: the server runs with the farm closed or the switch off: the button must end in the calculation, with
 * the design open (never a dead end). `--login`: walk as a signed-in customer (the form of /login), else as a guest.
 * Nothing paid is called: the walk stops before the payment.
 */
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
    return hit ? hit.slice(hit.indexOf('=') + 1) : fallback;
};
const BASE = String(arg('base', 'http://127.0.0.1:8046')).replace(/\/$/, '');
const OUT = String(arg('out', 'storage/app/order-walk'));
const TOOL = String(arg('tool', 'coaster'));
const WIDTH = Number(arg('width', 1280));
const EXPECT = String(arg('expect', 'order'));
const LOGIN = arg('login', null);
const NAME = String(arg('name', `${LOGIN ? 'user' : 'guest'}-${WIDTH}`));
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
mkdirSync(OUT, { recursive: true });

async function openChrome(port) {
    const profile = mkdtempSync(join(tmpdir(), 'mp-order-'));
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
    const { browserContextId } = await send('Target.createBrowserContext');
    const { targetId } = await send('Target.createTarget', { url: 'about:blank', browserContextId });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const close = async () => { try { await send('Browser.close'); } catch { /* gone */ } ws.close(); proc.kill(); await sleep(400); try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ } };
    return { cdp: (method, params) => send(method, params, sessionId), close };
}

const browser = await openChrome(9800 + Math.floor(Math.random() * 150));
const { cdp } = browser;
const evaluate = async (expression) => (await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result.value;
const until = async (expression, ms, step = 300) => {
    const end = Date.now() + ms;
    while (Date.now() < end) {
        try { if (await evaluate(expression)) return true; } catch { /* the page is changing */ }
        await sleep(step);
    }
    return false;
};
const steps = [];
let n = 0;
/** One step of the walk: its picture, what the page said, and whether it ended as described. */
const step = async (title, ok, said = {}) => {
    n++;
    const file = `${NAME}-${String(n).padStart(2, '0')}.webp`;
    const shot = await cdp('Page.captureScreenshot', { format: 'webp', quality: 70 });
    writeFileSync(join(OUT, file), Buffer.from(shot.data, 'base64'));
    const sideways = await evaluate('document.documentElement.scrollWidth > innerWidth + 1');
    const errors = await evaluate('window.__errs || []');
    const good = !!ok && !sideways && errors.length === 0;
    steps.push({ n, title, ok: good, file, sideways, errors, ...said });
    console.log(`${good ? 'OK ' : 'NO '} ${String(n).padStart(2)} ${title}${sideways ? '  ! the page scrolls sideways' : ''}${errors.length ? `  ! ${errors.join(' | ')}` : ''}`);
    Object.entries(said).forEach(([k, v]) => console.log(`        ${k}: ${typeof v === 'string' ? v.replace(/\s+/g, ' ').slice(0, 260) : JSON.stringify(v)}`));
    return good;
};
/** What the order page shows right now. */
const ORDER = `(() => {
    const el = (id) => document.getElementById(id);
    const shown = (id) => { const e = el(id); return !!e && !e.classList.contains('hidden') && !e.hidden && e.getClientRects().length > 0; };
    const text = (id) => (shown(id) ? el(id).innerText.replace(/\\s+/g, ' ').trim() : null);
    const s = window.MP_FARM?.state ?? {};
    return {
        url: location.pathname,
        status: text('farm-status'), await_note: text('farm-await-note'), stale_note: text('farm-stale-note'),
        price: shown('farm-result') ? text('farm-price') : null, greyed: !!el('farm-result')?.classList.contains('opacity-50'),
        estimate: shown('farm-estimate'), copies_line: text('farm-copies-line'),
        start: shown('farm-slice-start') ? { label: text('farm-slice-start'), disabled: el('farm-slice-start').disabled, hint: text('farm-slice-hint') } : null,
        reslice: shown('farm-reslice') ? text('farm-reslice') : null,
        pay: shown('farm-pay') ? { label: text('farm-pay-btn'), disabled: el('farm-pay-btn').getAttribute('aria-disabled'), error: text('farm-pay-error') } : null,
        price_bar: !!el('farm-price-bar') && !el('farm-price-bar').hidden && el('farm-price-bar').getClientRects().length > 0,
        pay_bar: !!el('farm-bar') && !el('farm-bar').hidden && el('farm-bar').getClientRects().length > 0,
        colors: document.querySelectorAll('#farm-colors [data-tile]').length, picked: document.querySelectorAll('#farm-colors [aria-checked="true"]').length,
        face_down: text('farm-face-down'), tool_link: shown('farm-tool-link') ? el('farm-tool-link').getAttribute('href') : null,
        boot: { status: s.status, on_demand: s.on_demand, big: s.big, face_down: s.face_down },
    };
})()`;

let failed = false;
try {
    await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable');
    await cdp('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: WIDTH < 600 ? 844 : 900, deviceScaleFactor: 1, mobile: WIDTH < 600 });
    await cdp('Network.setUserAgentOverride', { userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 HeadlessChrome matplace-review', acceptLanguage: 'cs' });
    await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
    await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
    await cdp('Page.addScriptToEvaluateOnNewDocument', { source: "window.__errs = []; window.addEventListener('error', (e) => window.__errs.push(String(e.message))); window.addEventListener('unhandledrejection', (e) => window.__errs.push('rejection: ' + String(e.reason && e.reason.message || e.reason)));" });

    if (LOGIN) {
        const [email, password] = String(LOGIN).split(':');
        await cdp('Page.navigate', { url: `${BASE}/login` });
        await until("document.readyState === 'complete' && !!document.querySelector('input[name=email]')", 20000);
        await evaluate(`(() => { document.querySelector('input[name=email]').value = ${JSON.stringify(email)}; document.querySelector('input[name=password]').value = ${JSON.stringify(password)}; document.querySelector('input[name=password]').closest('form').submit(); })()`);
        await until("!location.pathname.includes('/login') && document.readyState === 'complete'", 20000);
        await step('signed in', await evaluate("!location.pathname.includes('/login')"), { url: await evaluate('location.pathname') });
    }

    /** The tool's page with its design drawn; presses the main button and says where it ended. */
    const fromTool = async (url, title) => {
        await cdp('Page.navigate', { url });
        if (!await until("document.readyState === 'complete' && !!document.getElementById('tool-go')", 30000)) throw new Error('the tool page did not load');
        await until("(() => { const b = document.getElementById('tool-go'); return !!b && !b.disabled; })()", 60000, 500);
        await sleep(1500);
        const label = await evaluate("document.getElementById('tool-go-label').textContent.trim()");
        const hint = await evaluate("document.querySelector('#tool-go-card p.text-muted')?.textContent.trim() ?? ''");
        const direct = await evaluate('!!window.MP_TOOL?.order');
        await step(title, EXPECT === 'order' ? label === 'Přejít k tisku' && direct : label !== 'Přejít k tisku' && !direct, { label, hint, direct });
        await evaluate("document.getElementById('tool-go').click()");
        const left = await until("location.pathname.includes('/farm/orders/') || location.search.includes('open=')", 90000, 400);
        await until("document.readyState === 'complete'", 20000);
        return left ? await evaluate("location.pathname.includes('/farm/orders/') ? 'order' : 'calc'") : 'nowhere';
    };

    const where = await fromTool(`${BASE}/tools/${TOOL}`, `the tool's page: ${TOOL}`);
    if (EXPECT === 'calc') {
        // the farm closed or the switch off: the calculation with the design open, as before
        await until("!!document.getElementById('result') && !document.getElementById('result').classList.contains('hidden')", 60000, 500);
        await sleep(6000);
        const calc = await evaluate(`(() => ({ url: location.pathname + location.search, result: !document.getElementById('result')?.classList.contains('hidden'), cta: document.getElementById('cta-farm')?.innerText.trim() ?? null,
            note: (() => { const e = document.getElementById('cta-farm-note'); return e && !e.classList.contains('hidden') ? e.innerText.trim() : null; })(),
            edit: (() => { const e = document.getElementById('edit-design-link'); return e && !e.classList.contains('hidden') ? e.getAttribute('href') : null; })(),
            face_down: (() => { const e = document.getElementById('face-down-note'); return e && !e.classList.contains('hidden') ? e.innerText.trim() : null; })() }))()`);
        failed = !await step('the calculation with the design open (the fallback)', where === 'calc' && calc.result && !!calc.edit, calc) || failed;
    } else {
        if (where !== 'order') throw new Error(`the button ended ${where}, not on the order page`);
        await until("!!window.MP_FARM && document.querySelectorAll('#farm-colors [data-tile]').length > 0", 30000);
        await sleep(4000);    // the model is drawn
        let s = await evaluate(ORDER);
        const first = s.url;
        failed = !await step('the order page waits for the choices: no price, nothing sliced', s.boot.status === 'awaiting_choices' && s.boot.on_demand === true && s.price === null && !s.estimate && !!s.await_note
            && s.start?.label === 'Spočítat cenu' && s.start.disabled === false && s.pay === null && s.colors > 0 && s.picked === 1 && (WIDTH >= 1024 ? !s.price_bar : s.price_bar) && !!s.tool_link, s) || failed;
        // the choices: another colour, two pieces – still nothing is sliced
        await evaluate("(() => { const tiles = [...document.querySelectorAll('#farm-colors [data-tile] button[role=radio]:not([disabled])')]; (tiles.find((b) => b.getAttribute('aria-checked') !== 'true') ?? tiles[0]).click(); const c = document.getElementById('farm-copies'); c.value = '2'; c.dispatchEvent(new Event('input', { bubbles: true })); })()");
        await sleep(2500);
        s = await evaluate(ORDER);
        const still = await evaluate(`fetch(location.pathname + '/status', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => r.json()).then((j) => ({ status: j.status, slice_run: j.slice_run }))`);
        failed = !await step('a colour and two pieces chosen: still no slice', s.boot.status === 'awaiting_choices' && still.status === 'awaiting_choices' && still.slice_run === 0 && s.price === null, { ...still, picked: s.picked }) || failed;
        // "Spočítat cenu"
        await evaluate("document.getElementById('farm-slice-start').scrollIntoView({ block: 'center' })");
        await sleep(300);
        await step('the button in view', true, { hint: s.start?.hint });
        await evaluate("document.getElementById('farm-slice-start').click()");
        const priced = await until("(() => { const p = document.getElementById('farm-price'); const r = document.getElementById('farm-result'); return !!r && !r.classList.contains('hidden') && !r.classList.contains('opacity-50') && /\\d/.test(p.textContent) && !document.getElementById('farm-pay').classList.contains('hidden'); })()", 120000, 500);
        await sleep(2500);
        await evaluate('window.scrollTo(0, 0)');
        await sleep(300);
        s = await evaluate(ORDER);
        const price1 = s.price;
        failed = !await step('the price after the button', priced && !!s.price && !s.greyed && s.stale_note === null && s.start === null && s.pay !== null && s.pay_bar && !s.price_bar, s) || failed;
        // another number of pieces: nothing slices by itself, the price is out of date, the payment is held back
        await evaluate("(() => { const c = document.getElementById('farm-copies'); c.value = '4'; c.dispatchEvent(new Event('input', { bubbles: true })); })()");
        await sleep(3000);
        s = await evaluate(ORDER);
        const run = await evaluate(`fetch(location.pathname + '/status', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => r.json()).then((j) => ({ status: j.status, slice_run: j.slice_run, copies: j.copies }))`);
        await evaluate("document.getElementById('farm-pay-btn').click()");
        await sleep(900);
        const held = await evaluate(ORDER);
        failed = !await step('four pieces: the price out of date, one button, no payment', run.slice_run === 1 && run.copies === 2 && s.greyed && !!s.stale_note && s.reslice === 'Přepočítat cenu' && s.price === price1
            && s.pay?.disabled === 'true' && /přepočítejte/i.test(held.pay?.error ?? ''), { ...run, stale_note: s.stale_note, reslice: s.reslice, pay_error: held.pay?.error }) || failed;
        // "Přepočítat cenu"
        await evaluate("document.getElementById('farm-reslice').click()");
        const again = await until(`(() => { const r = document.getElementById('farm-result'); const st = document.getElementById('farm-stale-note'); return !r.classList.contains('opacity-50') && st.classList.contains('hidden') && document.getElementById('farm-reslice').classList.contains('hidden') && window.MP_FARM && /\\d/.test(document.getElementById('farm-price').textContent); })()`, 120000, 500);
        await sleep(2500);
        await evaluate('window.scrollTo(0, 0)');
        await sleep(300);
        s = await evaluate(ORDER);
        const run2 = await evaluate(`fetch(location.pathname + '/status', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => r.json()).then((j) => ({ status: j.status, slice_run: j.slice_run, copies: j.copies }))`);
        failed = !await step('recalculated: the price of four pieces', again && run2.slice_run === 2 && run2.copies === 4 && !s.greyed && s.stale_note === null && !!s.price, { ...run2, price_before: price1, price: s.price, copies_line: s.copies_line }) || failed;

        // back to the tool with the design open, and the button again. The price was asked for: this order stays, a new one waits
        const back = s.tool_link;
        const where2 = await fromTool(back, 'back in the tool with the design open');
        await until("!!window.MP_FARM && document.querySelectorAll('#farm-colors [data-tile]').length > 0", 30000);
        await sleep(3000);
        s = await evaluate(ORDER);
        const second = s.url;
        failed = !await step('the design sent again after its price was asked for: a new waiting order, the priced one stays', where2 === 'order' && second !== first && s.boot.status === 'awaiting_choices', { first, second }) || failed;
        // … and once more without asking for the price: the waiting order is the same one
        const where3 = await fromTool(s.tool_link, 'back in the tool once more');
        await until("!!window.MP_FARM && document.querySelectorAll('#farm-colors [data-tile]').length > 0", 30000);
        await sleep(3000);
        s = await evaluate(ORDER);
        failed = !await step('the design sent again before any price: the very same waiting order', where3 === 'order' && s.url === second && s.boot.status === 'awaiting_choices', { second, third: s.url, face_down: s.face_down }) || failed;
    }
} catch (e) {
    failed = true;
    try { await step(`the walk failed: ${e.message}`, false); } catch { console.log(`NO  the walk failed: ${e.message}`); }
} finally {
    await browser.close();
}
writeFileSync(join(OUT, `${NAME}.json`), JSON.stringify(steps, null, 2));
const bad = steps.filter((x) => !x.ok);
console.log(`\n${steps.length} steps, ${steps.length - bad.length} as described, ${bad.length} not. Pictures and ${NAME}.json: ${OUT}`);
process.exit(failed || bad.length ? 1 : 0);
