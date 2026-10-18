#!/usr/bin/env node
/**
 * The web as a visitor sees it (docs/AF.md §8, docs/AH.md §4): a fresh headless Chrome per page or path, a phone of
 * 360 × 800 or 390 × 844 (2×, touch), a tablet of 768 × 1024 or a laptop of 1280 × 800 (any width works: 1366 gives
 * 1366 × 768), the language by Accept-Language, the cookie choice made (consent=a0m0) so the bar does not cover the page
 * (--bar keeps it and measures what it covers). No packages: Chrome is driven over its DevTools protocol with Node's own
 * WebSocket (Node 22+). The user agent says "HeadlessChrome", so our own statistics count the run as a robot, not as
 * people (app/Support/Bots.php).
 *
 * Pages: a webp of the first screen and a line of facts per page and width:
 *   is the h1 / the sentence / the main action on the first screen; the viewer; the cards of the catalogue;
 *   hScroll: the page is wider than the window (scrollWidth > innerWidth);
 *   small: buttons and links on the first screen smaller than 44 × 44 px (their words), main: the size of the main action;
 *   bar: what the cookie bar covers (with --bar); focus: with --tabs=N, how many of N Tab presses land on something
 *   visible in the window with a visible outline; --zoom=200 sets the text to 200 % (the root font size) first;
 *   keyboard: with --keyboard (phones), the window loses the height of an open keyboard (40 %) and every visible field
 *   gets the focus in turn: is it inside the window and not covered (by the cookie bar, a sticky row)?
 *
 *   node scripts/review_round2.mjs --base=http://127.0.0.1:8013 --out=docs/img/audit [--pages=qr,sign,box,figure,relief,tools,home]
 *        [--widths=360,390,768,1280] [--langs=cs,en] [--suffix=1] [--full] [--bar] [--zoom=200] [--tabs=12]
 *        [--then=<js run after load>] [--wait=4000] [--keyboard]
 *
 * Paths (--flows=a,b or --flows=all): a few steps each, a webp after every step (flow-<name>-<width>-<lang>-<n>.webp),
 * and for every step where it is and what it says (the address, the h1, an alert or a status line). FLOWS below lists
 * them: a file → price → order, a tool → calculation → order (and the login in the middle of it), a model → download,
 * QR with a web address and with Wi-Fi, a file that is not a model, a photo over the limit.
 *
 * Facts go to <out>/facts.json (merged with what is there) and to the console.
 */
import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a === `--${name}` || a.startsWith(`--${name}=`));
    if (!hit) return fallback;
    return hit.includes('=') ? hit.slice(hit.indexOf('=') + 1) : true;
};
const BASE = String(arg('base', 'http://127.0.0.1:8013')).replace(/\/$/, '');
const OUT = String(arg('out', 'docs/img/web-round2'));
const FLOWS_ASKED = arg('flows', null);
const PAGES = FLOWS_ASKED ? [] : String(arg('pages', 'qr,sign,box,figure,relief,tools')).split(',');
const WIDTHS = String(arg('widths', '360,390,768,1280')).split(',').map(Number);
const LANGS = String(arg('langs', 'cs,en')).split(',');
const SUFFIX = String(arg('suffix', '1'));
const WAIT = Number(arg('wait', 4000));
const THEN = arg('then', null);
const ZOOM = Number(arg('zoom', 0)) || 0;
const TABS = Number(arg('tabs', 0)) || 0;
const BAR = Boolean(arg('bar', false));
const KEYBOARD = Boolean(arg('keyboard', false));
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PATHS = { tools: '/tools', home: '/' };
const pathOf = (page, lang) => {
    const p = page.startsWith('/') ? page : PATHS[page] ?? `/tools/${page.replace(/_/g, '-')}`;
    // English lives under /en (the site's own prefix); Czech is the default
    return lang === 'cs' ? p : `/${lang}${p === '/' ? '' : p}`;
};
const SIZE = { 360: [800, 2], 390: [844, 2], 768: [1024, 2], 1280: [800, 1], 1366: [768, 1] };
const screen = (width) => ({ height: SIZE[width]?.[0] ?? (width < 1024 ? 844 : 800), scale: SIZE[width]?.[1] ?? (width < 1024 ? 2 : 1), touch: width < 1024 });
const UA = {
    touch: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1 HeadlessChrome matplace-review',
    desk: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 HeadlessChrome matplace-review',
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Files a path hands to an upload field (written once into a temp folder). */
const FIXTURES = (() => {
    const dir = mkdtempSync(join(tmpdir(), 'mp-review-files-'));
    // a 20 mm cube as a binary STL with unit normals (12 triangles)
    const v = [[0, 0, 0], [20, 0, 0], [20, 20, 0], [0, 20, 0], [0, 0, 20], [20, 0, 20], [20, 20, 20], [0, 20, 20]];
    const f = [[0, 2, 1, [0, 0, -1]], [0, 3, 2, [0, 0, -1]], [4, 5, 6, [0, 0, 1]], [4, 6, 7, [0, 0, 1]], [0, 1, 5, [0, -1, 0]], [0, 5, 4, [0, -1, 0]],
        [2, 3, 7, [0, 1, 0]], [2, 7, 6, [0, 1, 0]], [0, 4, 7, [-1, 0, 0]], [0, 7, 3, [-1, 0, 0]], [1, 2, 6, [1, 0, 0]], [1, 6, 5, [1, 0, 0]]];
    const stl = Buffer.alloc(84 + 50 * f.length);
    stl.write('matplace review cube', 0); stl.writeUInt32LE(f.length, 80);
    f.forEach(([a, b, c, n], i) => {
        const o = 84 + 50 * i;
        [n, v[a], v[b], v[c]].flat().forEach((x, j) => stl.writeFloatLE(x, o + 4 * j));
    });
    writeFileSync(join(dir, 'cube.stl'), stl);
    writeFileSync(join(dir, 'broken.stl'), 'this is not a model\n');
    // 16 MB that say they are a JPEG: over the limit of the relief, and no browser can draw them
    const big = Buffer.alloc(16 * 1024 * 1024, 7); big[0] = 0xff; big[1] = 0xd8; big[2] = 0xff;
    writeFileSync(join(dir, 'big.jpg'), big);
    return { dir, cube: join(dir, 'cube.stl'), broken: join(dir, 'broken.stl'), big: join(dir, 'big.jpg') };
})();

/** The order button of the calculation, whatever its words are this week. */
const ORDER = /Objednat tisk|Vytisknout u nás|Chci to vyrobit|Order the print|Print it with us|I want it made/i;

/**
 * The paths of the audit (point 13). Steps: go (a page), file (selector, path), type (selector, text), click (selector),
 * clickText (a pattern of the words of a button or a link), nav (the step that follows loads a page), wait, shot,
 * reload (the page again, as F5), read (a JS expression whose value is written to the facts).
 */
const FLOWS = {
    'file-price': [['go', '/'], ['file', '#file-input', FIXTURES.cube], ['wait', 25000], ['shot'], ['nav'], ['clickText', ORDER], ['wait', 5000], ['shot']],
    'tool-price': [['go', '/tools/qr'], ['wait', 6000], ['shot'], ['nav'], ['click', '#tool-go'], ['wait', 20000], ['shot'], ['nav'], ['clickText', ORDER], ['wait', 5000], ['shot']],
    'download': [['go', '/tools/box'], ['wait', 6000], ['click', '#tool-download'], ['wait', 600], ['shot']],
    'qr-web': [['go', '/tools/qr'], ['wait', 4000], ['type', '[data-text="url"]', 'https://matplace.com/tools'], ['wait', 6000], ['shot']],
    // Wi-Fi has its own fields since 17 Oct 2026 (docs/AI.md): the kind, the network, the password
    'qr-wifi': [['go', '/tools/qr'], ['wait', 4000], ['click', '[data-choice="content"][value="wifi"]'], ['wait', 800], ['type', '[data-text="ssid"]', 'Domov'], ['type', '[data-text="password"]', 'heslo 123;"x'], ['wait', 6000], ['shot']],
    'bad-file': [['go', '/'], ['file', '#file-input', FIXTURES.broken], ['wait', 12000], ['shot']],
    // the calculation keeps what was set: 3 pieces, recalculated, the page reloaded (and back from the next page)
    'calc-keep': [['go', '/'], ['file', '#file-input', FIXTURES.cube], ['wait', 20000], ['type', '#quantity', '3'], ['click', '#cta-recalc'], ['wait', 12000], ['read', "document.getElementById('quantity').value + ' ks, ' + location.pathname"], ['reload'], ['wait', 6000], ['read', "document.getElementById('quantity').value + ' ks, ' + location.pathname + ', ' + document.getElementById('status').textContent"], ['shot']],
    'photo-limit': [['go', '/tools/relief'], ['wait', 3000], ['file', '#relief-photo', FIXTURES.big], ['wait', 1000], ['click', '#relief-form button.btn-ink'], ['wait', 4000], ['shot']],
};

/** One Chrome with a fresh profile; resolves to a small CDP client bound to one page. */
async function openChrome(port) {
    const profile = mkdtempSync(join(tmpdir(), 'mp-review-'));
    const proc = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', 'about:blank'], { stdio: 'ignore' });
    let version = null;
    for (let i = 0; i < 60 && !version; i++) {
        await sleep(250);
        try { version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); } catch { /* not up yet */ }
    }
    if (!version) throw new Error('Chrome did not start');
    const ws = new WebSocket(version.webSocketDebuggerUrl);
    await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
    let id = 0; const waiting = new Map(); const listeners = [];
    ws.onmessage = (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id && waiting.has(msg.id)) { const { res, rej } = waiting.get(msg.id); waiting.delete(msg.id); msg.error ? rej(new Error(msg.error.message)) : res(msg.result); }
        else listeners.forEach((l) => l(msg));
    };
    const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const n = ++id; waiting.set(n, { res, rej }); ws.send(JSON.stringify({ id: n, method, params, sessionId })); });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const page = (method, params) => send(method, params, sessionId);
    const once = (method, ms = 30000) => new Promise((r) => {
        const timer = setTimeout(() => r(null), ms);
        listeners.push((msg) => { if (msg.method === method && msg.sessionId === sessionId) { clearTimeout(timer); r(msg.params); } });
    });
    const on = (method, cb) => listeners.push((msg) => { if (msg.method === method && msg.sessionId === sessionId) cb(msg.params); });
    const close = async () => { try { await send('Browser.close'); } catch { /* gone */ } ws.close(); proc.kill(); await sleep(400); try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ } };
    return { page, once, on, close };
}

/** What is on the first screen, read in the page. */
const FACTS = `(() => {
    const H = innerHeight, W = innerWidth;
    // the width asked for (a phone's layout viewport grows with what sticks out, so innerWidth would hide it)
    const W0 = window.__mpWidth || W;
    const seen = (el) => { if (!el) return false; const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && r.top < H && r.bottom > 0 && r.left < W && s.visibility !== 'hidden' && s.display !== 'none'; };
    const whole = (el) => { if (!seen(el)) return false; const r = el.getBoundingClientRect(); return r.top >= 0 && r.bottom <= H; };
    const q = (s) => document.querySelector(s);
    const words = (el) => ((el.id ? '#' + el.id + ' ' : '') + (el.getAttribute('aria-label') || el.textContent || el.getAttribute('title') || el.getAttribute('name') || '')).trim().replace(/\\s+/g, ' ').slice(0, 40);
    const h1 = q('h1');
    // the sentence under the name: its own id on the tool page; elsewhere the paragraph after the row of the h1
    const lead = q('#tool-lead') || (h1 && (h1.parentElement.classList.contains('flex') ? h1.parentElement.nextElementSibling : h1.nextElementSibling));
    const go = q('#tool-go') || q('.btn-primary');
    const viewer = q('#tool-viewer');
    const views = q('#tool-views');
    const cards = [...document.querySelectorAll('[data-tool-section] [data-cats], #tool-picks [data-key]')].filter(seen);
    const fixed = [...document.querySelectorAll('body *')].filter((el) => { const p = getComputedStyle(el).position; return (p === 'fixed' || p === 'sticky') && seen(el); }).map((el) => el.id || el.tagName.toLowerCase());
    // what a finger has to hit: links and buttons on the first screen smaller than 44 x 44 (inline links in a sentence excepted)
    const small = [...document.querySelectorAll('a[href], button, [role=button], input:not([type=hidden]), select, summary')].filter((el) => {
        if (!seen(el) || el.closest('p, li > p, .article')) return false;
        const r = el.getBoundingClientRect();
        return r.top < H && (r.width < 44 || r.height < 44) && !(el.type === 'checkbox' || el.type === 'radio' || el.type === 'range' || el.classList.contains('sr-only'));
    }).map((el) => { const r = el.getBoundingClientRect(); return words(el) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height); });
    const mr = go && seen(go) ? go.getBoundingClientRect() : null;
    const bar = q('#consent');
    let covers = null;
    if (bar && seen(bar)) {
        const b = bar.getBoundingClientRect();
        const hit = (el) => { if (!el || !seen(el)) return false; const r = el.getBoundingClientRect(); return r.bottom > b.top && r.top < b.bottom; };
        covers = { height: Math.round(b.height), go: hit(go), h1: hit(h1) };
    }
    const alert = [...document.querySelectorAll('[role=alert], [role=status], [aria-live], .note-error, .note-warn, #tool-error')].filter(seen).map((el) => el.textContent.trim().replace(/\\s+/g, ' ').slice(0, 160)).filter(Boolean);
    return {
        path: location.pathname + location.search, h1: h1 ? h1.textContent.trim().replace(/\\s+/g, ' ') : null, h1Seen: whole(h1), leadSeen: whole(lead),
        goSeen: whole(go), goLabel: go ? go.textContent.trim().replace(/\\s+/g, ' ') : null, main: mr ? Math.round(mr.width) + 'x' + Math.round(mr.height) : null,
        viewerSeen: seen(viewer), viewerHeight: viewer ? Math.round(viewer.getBoundingClientRect().height) : null,
        sampleSeen: seen(q('#tool-sample')), viewButtonsSeen: views ? [...views.querySelectorAll('button')].filter(seen).length : null,
        cardsSeen: cards.length, firstCard: cards[0] ? cards[0].textContent.trim().replace(/\\s+/g, ' ').slice(0, 60) : null,
        hScroll: document.documentElement.scrollWidth > W0 + 1, scrollWidth: document.documentElement.scrollWidth,
        // what sticks out: the innermost elements whose right edge is past the width
        wide: [...document.querySelectorAll('body *')].filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.right > W0 + 1 && ![...el.children].some((c) => c.getBoundingClientRect().right > W0 + 1); })
            .slice(0, 6).map((el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + ' ' + (el.getAttribute('class') || '').split(' ').slice(0, 3).join('.') + ' ' + Math.round(el.getBoundingClientRect().right)),
        // words that stick out of their box (a word too long for a column at 200 %), outside boxes that scroll or clip (sr-only)
        wideText: (() => { const out = []; const scrolls = (el) => { for (let p = el; p; p = p.parentElement) { const o = getComputedStyle(p).overflowX; if (o !== 'visible') return true; } return false; };
            const tw = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n;
            while ((n = tw.nextNode()) && out.length < 6) { if (!n.textContent.trim()) continue; const r = document.createRange(); r.selectNodeContents(n); const b = r.getBoundingClientRect();
                if (b.width > 0 && b.right > W0 + 1 && !scrolls(n.parentElement)) out.push(n.parentElement.tagName.toLowerCase() + (n.parentElement.id ? '#' + n.parentElement.id : '') + ' ' + n.textContent.trim().slice(0, 30) + ' ' + Math.round(b.right)); }
            return out; })(),
        small: small.slice(0, 12), smallCount: small.length, bar: covers, says: alert.slice(0, 4),
        // a tool page: empty (nothing yet), loading (busy badge or the logo of dots), a model (its size in the status line) or an error
        state: !q('#tool-status') ? null : [...document.querySelectorAll('#tool-error, .note-error')].some(seen) ? 'error'
            : (seen(q('#tool-busy')) || q('[data-particles]')) ? 'loading' : /\\d\\s*mm/.test(q('#tool-status').textContent) ? 'model' : 'empty',
        pageHeight: document.documentElement.scrollHeight, fixed,
    };
})()`;

/** Where the keyboard is after a Tab: something visible in the window, with an outline or a ring one can see. */
const FOCUSED = `(() => {
    const el = document.activeElement;
    if (!el || el === document.body) return { ok: false, what: 'body' };
    const r = el.getBoundingClientRect(); const s = getComputedStyle(el);
    const inView = r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight;
    const ring = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || s.boxShadow !== 'none';
    return { ok: inView && ring, what: (el.id ? '#' + el.id : el.tagName.toLowerCase()) + ' ' + (el.textContent || el.getAttribute('aria-label') || '').trim().slice(0, 30) };
})()`;

/** Every visible field gets the focus as a tap would give it: is it in the window, and is it what a finger hits there? */
const TYPED = `(async () => {
    const fields = [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=range]):not([type=file]):not([type=submit]), textarea, select')]
        .filter((el) => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && !el.closest('.hidden, [hidden], .sr-only, .is-off, details:not([open]) > :not(summary)') && !el.disabled; }).slice(0, 12);
    const out = [];
    for (const el of fields) {
        el.focus();
        // a phone brings the focused field into view, above the keyboard, keeping the page's scroll padding
        el.scrollIntoView({ block: 'nearest' });
        await new Promise((r) => setTimeout(r, 150));
        const r = el.getBoundingClientRect();
        const inside = r.top >= 0 && r.bottom <= innerHeight;
        const hit = document.elementFromPoint(r.left + Math.min(12, r.width / 2), r.top + r.height / 2);
        const covered = !hit || !(hit === el || el.contains(hit) || hit.contains(el) || (hit.tagName === 'LABEL' && hit.control === el));
        out.push({ field: (el.id ? '#' + el.id : el.name ? el.tagName.toLowerCase() + '[' + el.name + ']' : el.tagName.toLowerCase() + [...el.attributes].filter((a) => a.name.startsWith('data-') || a.name === 'type').map((a) => '[' + a.name + '=' + a.value.slice(0, 16) + ']').join('')) + ' ' + Math.round(r.top) + '..' + Math.round(r.bottom) + '/' + innerHeight, ok: inside && !covered, by: covered && hit ? (hit.closest('[id]')?.id || hit.tagName.toLowerCase()) : null });
        el.blur();
    }
    return out;
})()`;

async function prepare(cdp, width, lang) {
    const s = screen(width);
    await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable'); await cdp('DOM.enable');
    await cdp('Emulation.setDeviceMetricsOverride', { width, height: s.height, deviceScaleFactor: s.scale, mobile: s.touch });
    if (s.touch) await cdp('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
    await cdp('Network.setUserAgentOverride', { userAgent: s.touch ? UA.touch : UA.desk, acceptLanguage: lang });
    await cdp('Network.setExtraHTTPHeaders', { headers: { 'Accept-Language': lang } });
    // our own test run: left out of the statistics (TODO #10, docs/AJ.md)
    await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
    if (!BAR) await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
}

const evaluate = async (cdp, expression) => (await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result.value;

async function measure(cdp, width) {
    await evaluate(cdp, `window.__mpWidth = ${width}`);
    if (ZOOM) await evaluate(cdp, `document.documentElement.style.fontSize = '${ZOOM}%'`);
    const facts = await evaluate(cdp, FACTS);
    if (KEYBOARD && screen(width).touch) {
        const s = screen(width);
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: Math.round(s.height * 0.6), deviceScaleFactor: s.scale, mobile: true });
        await sleep(300);
        const typed = await evaluate(cdp, TYPED);
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: s.height, deviceScaleFactor: s.scale, mobile: true });
        facts.keyboard = `${typed.filter((t) => t.ok).length}/${typed.length}`;
        facts.keyboardMissed = typed.filter((t) => !t.ok).map((t) => t.field + (t.by ? ' under ' + t.by : ' outside'));
    }
    if (TABS) {
        const seen = [];
        for (let i = 0; i < TABS; i++) {
            await cdp('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            await cdp('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            await sleep(120);
            seen.push(await evaluate(cdp, FOCUSED));
        }
        facts.focus = `${seen.filter((f) => f.ok).length}/${TABS}`;
        facts.focusMissed = seen.filter((f) => !f.ok).map((f) => f.what).slice(0, 5);
    }
    return facts;
}

async function shotTo(cdp, file, width, full = false, height = 0) {
    const s = screen(width);
    const shot = full
        ? await cdp('Page.captureScreenshot', { format: 'webp', quality: 55, captureBeyondViewport: true, clip: { x: 0, y: 0, width, height: Math.min(height, 16000), scale: s.touch ? 0.25 : 0.5 } })
        : await cdp('Page.captureScreenshot', { format: 'webp', quality: 78 });
    writeFileSync(join(OUT, file), Buffer.from(shot.data, 'base64'));
}

async function shoot(page, width, lang, port) {
    const { page: cdp, once, on, close } = await openChrome(port);
    const errors = [];
    on('Runtime.exceptionThrown', (p) => errors.push((p.exceptionDetails?.exception?.description ?? p.exceptionDetails?.text ?? '').split('\n')[0].slice(0, 120)));
    try {
        await prepare(cdp, width, lang);
        const loaded = once('Page.loadEventFired');
        await cdp('Page.navigate', { url: BASE + pathOf(page, lang) });
        await loaded;
        await sleep(WAIT);
        if (THEN) { await evaluate(cdp, String(THEN)); await sleep(WAIT); }
        const name = `${page.replace(/[^a-z0-9-]+/gi, '-').replace(/^-|-$/g, '') || 'home'}-${width}-${lang}-${SUFFIX}${ZOOM ? `-zoom${ZOOM}` : ''}${BAR ? '-bar' : ''}`;
        const facts = await measure(cdp, width);
        await shotTo(cdp, `${name}.webp`, width);
        if (arg('full', false)) await shotTo(cdp, `${name.replace(/-[^-]+$/, '')}-full.webp`, width, true, facts.pageHeight);
        return { name, url: BASE + pathOf(page, lang), ...facts, errors };
    } finally {
        await close();
    }
}

/** One path of the audit: every step leaves a picture and where it is. */
async function walk(flow, width, lang, port) {
    const { page: cdp, once, close } = await openChrome(port);
    const steps = [];
    let n = 0; let navigation = null;
    try {
        await prepare(cdp, width, lang);
        for (const [what, a, b] of FLOWS[flow]) {
            if (what === 'go') { const loaded = once('Page.loadEventFired'); await cdp('Page.navigate', { url: BASE + pathOf(a, lang) }); await loaded; await sleep(1500); }
            else if (what === 'nav') navigation = once('Page.loadEventFired', 30000);
            else if (what === 'reload') { const loaded = once('Page.loadEventFired'); await cdp('Page.reload', {}); await loaded; await sleep(1500); }
            else if (what === 'read') steps.push({ step: 'read', value: await evaluate(cdp, a) });
            else if (what === 'wait') { if (navigation) { await navigation; navigation = null; await sleep(1500); } await sleep(a); }
            else if (what === 'file') {
                const { root } = await cdp('DOM.getDocument', { depth: 0 });
                const { nodeId } = await cdp('DOM.querySelector', { nodeId: root.nodeId, selector: a });
                if (!nodeId) { steps.push({ step: `file ${a}`, missing: true }); continue; }
                await cdp('DOM.setFileInputFiles', { files: [resolve(b)], nodeId });
            } else if (what === 'type') {
                await evaluate(cdp, `(() => { const el = document.querySelector(${JSON.stringify(a)}); if (!el) return false; el.focus(); el.value = ${JSON.stringify(b)}; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); return true; })()`);
            } else if (what === 'click' || what === 'clickText') {
                const found = await evaluate(cdp, what === 'click'
                    ? `(() => { const el = document.querySelector(${JSON.stringify(a)}); if (!el || el.disabled) return el ? 'disabled' : null; el.scrollIntoView({ block: 'center' }); el.click(); return (el.textContent || '').trim().slice(0, 60); })()`
                    : `(() => { const re = new RegExp(${JSON.stringify(a.source)}, 'i'); const el = [...document.querySelectorAll('a, button')].find((x) => re.test(x.textContent) && x.offsetParent); if (!el) return null; el.scrollIntoView({ block: 'center' }); el.click(); return el.textContent.trim().slice(0, 60); })()`);
                steps.push({ step: `${what} ${what === 'click' ? a : a.source}`, clicked: found });
                if (!found || found === 'disabled') navigation = null;
            } else if (what === 'shot') {
                if (navigation) { await navigation; navigation = null; await sleep(2000); }
                n++;
                const name = `flow-${flow}-${width}-${lang}-${n}`;
                const facts = await measure(cdp, width);
                await shotTo(cdp, `${name}.webp`, width);
                steps.push({ step: `shot ${n}`, name, ...facts });
            }
        }
        return { name: `flow-${flow}-${width}-${lang}`, steps };
    } finally {
        await close();
    }
}

// "catalogue": every tool the catalogue lists (its links), so all pages are walked, not a hand-made list
if (PAGES.includes('catalogue')) {
    const html = await (await fetch(`${BASE}/tools`, { headers: { 'User-Agent': UA.desk } })).text();
    const links = [...new Set([...html.matchAll(/<a href="[^"]*?(\/tools\/[a-z0-9-]+)"[^>]*data-cats/g)].map((m) => m[1]))];
    PAGES.splice(PAGES.indexOf('catalogue'), 1, ...links);
}
mkdirSync(OUT, { recursive: true });
const factsFile = join(OUT, 'facts.json');
const all = existsSync(factsFile) ? JSON.parse(readFileSync(factsFile, 'utf8')) : {};
let port = 9341;
for (const page of PAGES) for (const width of WIDTHS) for (const lang of LANGS) {
    try {
        const f = await shoot(page, width, lang, port++);
        all[f.name] = f;
        console.log(JSON.stringify(f));
    } catch (e) {
        console.error(page, width, lang, e.message);
    }
}
const flows = FLOWS_ASKED === true || FLOWS_ASKED === 'all' ? Object.keys(FLOWS) : FLOWS_ASKED ? String(FLOWS_ASKED).split(',') : [];
for (const flow of flows) for (const width of WIDTHS) for (const lang of LANGS) {
    if (!FLOWS[flow]) { console.error('no such path:', flow); continue; }
    try {
        const f = await walk(flow, width, lang, port++);
        all[f.name] = f;
        console.log(JSON.stringify(f));
    } catch (e) {
        console.error(flow, width, lang, e.message);
    }
}
writeFileSync(factsFile, JSON.stringify(all, null, 2));
rmSync(FIXTURES.dir, { recursive: true, force: true });
