#!/usr/bin/env node
/**
 * The decorations of a tool page in a headless Chrome (docs/AR.md §12): a phone of 390 × 844 (2×, touch) or a laptop
 * of 1280 × 800, a list of steps from a JSON file, a PNG wherever the steps ask for one. No packages: Chrome is driven
 * over its DevTools protocol with Node's own WebSocket (Node 22+), as scripts/review_round2.mjs does. The user agent
 * says "HeadlessChrome", so the site's statistics count the run as a robot.
 *
 *   node scripts/decor_walk.mjs --base=http://127.0.0.1:8046 --steps=steps.json [--width=390] [--height=844] [--out=docs/img] [--port=9333]
 *        [--lang=cs] [--cookie=name=value]
 *
 * Steps (each an array):
 *   ["go", "/tools/medallion"]            a page
 *   ["wait", 3000]                        milliseconds
 *   ["until", "<js>", 20000]              until the expression is true (or the time runs out)
 *   ["eval", "<js>"]                      run in the page; its value is printed
 *   ["click", "<selector>"]               a click (a tap on a phone) in the middle of the element
 *   ["type", "<selector>", "text"]        a field's value, with its input event
 *   ["file", "<selector>", "path"]        a file handed to an upload field
 *   ["drag", "<js giving [x, y]>", [dx, dy]]   a press at the point of the window the expression gives, a drag, a release:
 *                                         touches on a phone, the mouse on a laptop
 *   ["shot", "name"]                      <out>/<name>.png of the window; ["shot", "name", "<selector>"] of one element
 */
import { spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
    return hit ? hit.slice(name.length + 3) : fallback;
};
const BASE = String(arg('base', 'http://127.0.0.1:8046')).replace(/\/$/, '');
const WIDTH = Number(arg('width', 390));
const OUT = String(arg('out', 'docs/img'));
const PORT = Number(arg('port', 9333));
const STEPS = JSON.parse(readFileSync(String(arg('steps', 'steps.json')), 'utf8'));
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PHONE = WIDTH < 1024;
const HEIGHT = Number(arg('height', PHONE ? 844 : 800));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const profile = mkdtempSync(join(tmpdir(), 'mp-decor-'));
const proc = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--enable-unsafe-swiftshader', 'about:blank'], { stdio: 'ignore' });
let version = null;
for (let i = 0; i < 60 && !version; i++) {
    await sleep(250);
    try { version = await (await fetch(`http://127.0.0.1:${PORT}/json/version`)).json(); } catch { /* not up yet */ }
}
if (!version) throw new Error('Chrome did not start');
const ws = new WebSocket(version.webSocketDebuggerUrl);
await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
let id = 0; const waiting = new Map(); const listeners = [];
ws.onmessage = (m) => {
    const msg = JSON.parse(m.data);
    if (msg.id && waiting.has(msg.id)) { const { res, rej } = waiting.get(msg.id); waiting.delete(msg.id); msg.error ? rej(new Error(msg.error.message)) : res(msg.result); } else listeners.forEach((l) => l(msg));
};
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const n = ++id; waiting.set(n, { res, rej }); ws.send(JSON.stringify({ id: n, method, params, sessionId })); });
const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
const cdp = (method, params) => send(method, params, sessionId);
const once = (method, ms = 30000) => new Promise((r) => {
    const timer = setTimeout(() => r(null), ms);
    listeners.push((msg) => { if (msg.method === method && msg.sessionId === sessionId) { clearTimeout(timer); r(msg.params); } });
});
const errors = [];
listeners.push((msg) => { if (msg.method === 'Runtime.exceptionThrown' && msg.sessionId === sessionId) errors.push((msg.params.exceptionDetails?.exception?.description ?? msg.params.exceptionDetails?.text ?? '').split('\n')[0].slice(0, 200)); });

await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable'); await cdp('DOM.enable');
await cdp('Emulation.setDeviceMetricsOverride', { width: WIDTH, height: HEIGHT, deviceScaleFactor: PHONE ? 2 : 1, mobile: PHONE });
if (PHONE) await cdp('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
await cdp('Network.setUserAgentOverride', {
    userAgent: PHONE ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1 HeadlessChrome matplace-review'
        : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 HeadlessChrome matplace-review',
    acceptLanguage: String(arg('lang', 'cs')),
});
await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
// --cookie=mp_sid=…: the visitor a stored design belongs to, so the page may open it again (?from=<uuid>)
for (const pair of process.argv.filter((a) => a.startsWith('--cookie=')).map((a) => a.slice(9))) {
    await cdp('Network.setCookie', { name: pair.slice(0, pair.indexOf('=')), value: pair.slice(pair.indexOf('=') + 1), url: BASE });
}

const evaluate = async (expression) => {
    const r = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description ?? r.exceptionDetails.text);
    return r.result.value;
};
const middle = (selector) => evaluate(`(() => { const e = document.querySelector(${JSON.stringify(selector)}); if (!e) return null; e.scrollIntoView({ block: 'center' }); const r = e.getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; })()`);
const press = async (x, y, dx, dy) => {
    if (PHONE) {
        await cdp('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
        for (let i = 1; i <= 12 && (dx || dy); i++) { await cdp('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + (dx * i) / 12, y: y + (dy * i) / 12 }] }); await sleep(30); }
        await cdp('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    } else {
        await cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
        await cdp('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
        for (let i = 1; i <= 12 && (dx || dy); i++) { await cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: x + (dx * i) / 12, y: y + (dy * i) / 12, button: 'left', buttons: 1 }); await sleep(30); }
        await cdp('Input.dispatchMouseEvent', { type: 'mouseReleased', x: x + dx, y: y + dy, button: 'left', clickCount: 1 });
    }
};

mkdirSync(OUT, { recursive: true });
let failed = false;
try {
    for (const [what, a, b] of STEPS) {
        if (what === 'go') { const loaded = once('Page.loadEventFired'); await cdp('Page.navigate', { url: BASE + a }); await loaded; await sleep(800); }
        else if (what === 'wait') await sleep(a);
        else if (what === 'until') {
            const end = Date.now() + (b ?? 20000); let ok = false;
            while (Date.now() < end && !(ok = await evaluate(`Boolean(${a})`))) await sleep(250);
            if (!ok) { console.log(`until: not reached: ${a}`); failed = true; }
        } else if (what === 'eval') console.log(`eval: ${JSON.stringify(await evaluate(a))}`);
        else if (what === 'click') {
            const at = await middle(a);
            if (!at) { console.log(`click: nothing at ${a}`); failed = true; continue; }
            await sleep(150);
            const [x, y] = await middle(a);
            await press(x, y, 0, 0);
        } else if (what === 'type') {
            await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(a)}); e.focus(); e.value = ${JSON.stringify(b)}; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); e.blur(); })()`);
        } else if (what === 'file') {
            const { root } = await cdp('DOM.getDocument', { depth: 0 });
            const { nodeId } = await cdp('DOM.querySelector', { nodeId: root.nodeId, selector: a });
            await cdp('DOM.setFileInputFiles', { files: [resolve(b)], nodeId });
        } else if (what === 'drag') {
            const at = await evaluate(a);
            if (!at) { console.log(`drag: no point from ${a}`); failed = true; continue; }
            await press(at[0], at[1], b[0], b[1]);
        } else if (what === 'shot') {
            let clip;
            if (b) {
                const r = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(b)}); if (!e) return null; e.scrollIntoView({ block: 'center' }); const r = e.getBoundingClientRect(); return [r.left, r.top, r.width, r.height]; })()`);
                if (r) clip = { x: Math.max(0, r[0]), y: Math.max(0, r[1]), width: r[2], height: Math.min(r[3], HEIGHT), scale: 1 };
                await sleep(200);
            }
            const { data } = await cdp('Page.captureScreenshot', { format: 'png', ...(clip ? { clip } : {}) });
            writeFileSync(join(OUT, `${a}.png`), Buffer.from(data, 'base64'));
            console.log(`shot: ${join(OUT, `${a}.png`)}`);
        }
    }
} finally {
    if (errors.length) console.log(`page errors: ${JSON.stringify([...new Set(errors)])}`);
    try { await send('Browser.close'); } catch { /* gone */ }
    ws.close(); proc.kill(); await sleep(400);
    try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ }
}
process.exit(failed ? 1 : 0);
