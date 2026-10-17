#!/usr/bin/env node
/**
 * The first screen of a page as a visitor sees it: a fresh headless Chrome per page, 390 × 844 (an iPhone, 2×, touch)
 * or 1366 × 768, the language by Accept-Language, the cookie choice made (consent=a0m0) so the bar does not cover the
 * page (pass --bar to keep it). For each page a webp of the first screen and a line of facts: is the h1 on the screen,
 * the sentence under it, the main action, the viewer, the first card of the catalogue. No packages: Chrome is driven
 * over its DevTools protocol with Node's own WebSocket (Node 22+). The user agent says "HeadlessChrome", so our own
 * statistics count the run as a robot, not as people (app/Support/Bots.php).
 *
 *   node scripts/review_round2.mjs --base=http://127.0.0.1:8013 --out=docs/img/web-round2 [--pages=qr,sign,box,figure,relief,tools]
 *        [--widths=390,1366] [--langs=cs,en] [--suffix=1] [--full] [--bar] [--then=<js run after load>] [--wait=4000]
 *
 * Facts go to <out>/facts.json (merged with what is there) and to the console. docs/AF.md §5 says how they were read.
 */
import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a === `--${name}` || a.startsWith(`--${name}=`));
    if (!hit) return fallback;
    return hit.includes('=') ? hit.slice(hit.indexOf('=') + 1) : true;
};
const BASE = String(arg('base', 'http://127.0.0.1:8013')).replace(/\/$/, '');
const OUT = String(arg('out', 'docs/img/web-round2'));
const PAGES = String(arg('pages', 'qr,sign,box,figure,relief,tools')).split(',');
const WIDTHS = String(arg('widths', '390,1366')).split(',').map(Number);
const LANGS = String(arg('langs', 'cs,en')).split(',');
const SUFFIX = String(arg('suffix', '1'));
const WAIT = Number(arg('wait', 4000));
const THEN = arg('then', null);
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PATHS = { tools: '/tools', home: '/' };
const pathOf = (page, lang) => {
    const p = PATHS[page] ?? `/tools/${page.replace(/_/g, '-')}`;
    // English lives under /en (the site's own prefix); Czech is the default
    return lang === 'cs' ? p : `/${lang}${p === '/' ? '' : p}`;
};
const UA = {
    390: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1 HeadlessChrome matplace-review',
    1366: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 HeadlessChrome matplace-review',
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

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
    const once = (method) => new Promise((r) => listeners.push((msg) => { if (msg.method === method && msg.sessionId === sessionId) r(msg.params); }));
    const close = async () => { try { await send('Browser.close'); } catch { /* gone */ } ws.close(); proc.kill(); await sleep(400); try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ } };
    return { page, once, close };
}

/** What is on the first screen, read in the page. */
const FACTS = `(() => {
    const H = innerHeight, W = innerWidth;
    const seen = (el) => { if (!el) return false; const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && r.top < H && r.bottom > 0 && r.left < W && s.visibility !== 'hidden' && s.display !== 'none'; };
    const whole = (el) => { if (!seen(el)) return false; const r = el.getBoundingClientRect(); return r.top >= 0 && r.bottom <= H; };
    const q = (s) => document.querySelector(s);
    const h1 = q('h1');
    // the sentence under the name: its own id on the new tool page; before it, the paragraph after the row of the h1
    const lead = q('#tool-lead') || (h1 && (h1.parentElement.classList.contains('flex') ? h1.parentElement.nextElementSibling : h1.nextElementSibling));
    const go = q('#tool-go');
    const viewer = q('#tool-viewer');
    const sample = q('#tool-sample');
    const views = q('#tool-views');
    const cards = [...document.querySelectorAll('[data-tool-section] [data-cats]')].filter(seen);
    const fixed = [...document.querySelectorAll('body *')].filter((el) => { const p = getComputedStyle(el).position; return (p === 'fixed' || p === 'sticky') && seen(el); }).map((el) => el.id || el.tagName.toLowerCase());
    return {
        h1: h1 ? h1.textContent.trim() : null, h1Seen: whole(h1), leadSeen: whole(lead),
        goSeen: whole(go), goLabel: go ? go.textContent.trim() : null,
        viewerSeen: seen(viewer), viewerHeight: viewer ? Math.round(viewer.getBoundingClientRect().height) : null,
        sampleSeen: seen(sample), viewButtonsSeen: views ? [...views.querySelectorAll('button')].filter(seen).length : null,
        cardsSeen: cards.length, firstCard: cards[0] ? cards[0].textContent.trim().replace(/\\s+/g, ' ').slice(0, 60) : null,
        priceSeen: seen(q('#tool-price')), pageHeight: document.documentElement.scrollHeight, fixed,
    };
})()`;

async function shoot(page, width, lang, port) {
    const { page: cdp, once, close } = await openChrome(port);
    try {
        const mobile = width < 1024;
        await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable');
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: mobile ? 844 : 768, deviceScaleFactor: mobile ? 2 : 1, mobile });
        if (mobile) await cdp('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
        await cdp('Network.setUserAgentOverride', { userAgent: UA[mobile ? 390 : 1366], acceptLanguage: lang });
        // our own test run: left out of the statistics (TODO #10, docs/AJ.md)
        await cdp('Network.setExtraHTTPHeaders', { headers: { 'Accept-Language': lang } });
        await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
        if (!arg('bar', false)) await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
        const loaded = once('Page.loadEventFired');
        await cdp('Page.navigate', { url: BASE + pathOf(page, lang) });
        await loaded;
        await sleep(WAIT);
        if (THEN) { await cdp('Runtime.evaluate', { expression: String(THEN), awaitPromise: true }); await sleep(WAIT); }
        const facts = (await cdp('Runtime.evaluate', { expression: FACTS, returnByValue: true })).result.value;
        const name = `${page}-${width}-${lang}-${SUFFIX}`;
        const shot = await cdp('Page.captureScreenshot', { format: 'webp', quality: 78 });
        writeFileSync(join(OUT, `${name}.webp`), Buffer.from(shot.data, 'base64'));
        if (arg('full', false)) {
            const h = Math.min(facts.pageHeight, 16000);
            const full = await cdp('Page.captureScreenshot', { format: 'webp', quality: 55, captureBeyondViewport: true, clip: { x: 0, y: 0, width, height: h, scale: mobile ? 0.25 : 0.5 } });
            writeFileSync(join(OUT, `${page}-${width}-${lang}-full.webp`), Buffer.from(full.data, 'base64'));
        }
        return { name, url: BASE + pathOf(page, lang), ...facts };
    } finally {
        await close();
    }
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
writeFileSync(factsFile, JSON.stringify(all, null, 2));
