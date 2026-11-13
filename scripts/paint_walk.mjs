#!/usr/bin/env node
/**
 * The colouring tool as a visitor uses it (docs/AS.md §6, §7): headless Chrome over its DevTools protocol (no packages,
 * as scripts/review_round2.mjs), a phone of 390 × 844 (2×, touch) or a laptop of 1280 × 800. A model is uploaded on
 * /tools/paint, a surface is filled with a tap, a stroke is drawn with the brush, a colour is added by height, the
 * colouring is saved and the calculation opens. A webp after every step and the numbers of the page: how long the model
 * took to open, how long a frame, a tap and a stroke take.
 *
 *   node scripts/paint_walk.mjs --base=http://127.0.0.1:8061 --file=<model.stl> [--out=docs/img/paint] [--widths=390,1280]
 *        [--name=cube] [--lang=cs] [--no-save] [--no-brush]
 */
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync, mkdtempSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a === `--${name}` || a.startsWith(`--${name}=`));
    if (!hit) return fallback;
    return hit.includes('=') ? hit.slice(hit.indexOf('=') + 1) : true;
};
const BASE = String(arg('base', 'http://127.0.0.1:8061')).replace(/\/$/, '');
const OUT = String(arg('out', 'docs/img/paint'));
const FILE = resolve(String(arg('file', 'tests/fixtures/cube10.stl')));
const NAME = String(arg('name', 'model'));
const LANG = String(arg('lang', 'cs'));
const WIDTHS = String(arg('widths', '390,1280')).split(',').map(Number);
const SAVE = !arg('no-save', false);
const BRUSH = !arg('no-brush', false);
// --card: instead of the walk, the model is coloured for a picture (two colours by height, a wavy stroke) and only the
// viewer is photographed, as <out>/<name>-card.png (what public/img/tools/paint-*.{jpg,webp} are made of)
const CARD = Boolean(arg('card', false));
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const SIZE = { 390: [844, 2, true], 1280: [800, 1, false] };
const UA = {
    touch: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/130.0.0.0 Mobile Safari/537.36',
    desk: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/130.0.0.0 Safari/537.36',
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function openChrome(port) {
    const profile = mkdtempSync(join(tmpdir(), 'mp-paint-'));
    const proc = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--enable-unsafe-swiftshader', 'about:blank'], { stdio: 'ignore' });
    let version = null;
    for (let i = 0; i < 60 && !version; i++) {
        await sleep(250);
        try { version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); } catch { /* not up yet */ }
    }
    if (!version) throw new Error('Chrome did not start');
    const ws = new WebSocket(version.webSocketDebuggerUrl);
    await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
    let id = 0; const waiting = new Map(); const errors = [];
    ws.onmessage = (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id && waiting.has(msg.id)) { const { res, rej } = waiting.get(msg.id); waiting.delete(msg.id); msg.error ? rej(new Error(msg.error.message)) : res(msg.result); }
        // what the page itself complains about: an exception, an error written to its console
        else if (msg.method === 'Runtime.exceptionThrown') errors.push(msg.params.exceptionDetails.exception?.description ?? msg.params.exceptionDetails.text);
        else if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') errors.push(msg.params.args.map((a) => a.value ?? a.description ?? '').join(' '));
    };
    const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const n = ++id; waiting.set(n, { res, rej }); ws.send(JSON.stringify({ id: n, method, params, sessionId })); });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const page = (method, params) => send(method, params, sessionId);
    const close = async () => { try { await send('Browser.close'); } catch { /* gone */ } ws.close(); proc.kill(); await sleep(400); try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ } };
    return { page, close, errors };
}

const evaluate = async (cdp, expression) => {
    const r = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (r.exceptionDetails) throw new Error(`page: ${r.exceptionDetails.exception?.description ?? r.exceptionDetails.text}`);
    return r.result.value;
};
const until = async (cdp, expression, ms = 120000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) { if (await evaluate(cdp, expression)) return true; await sleep(250); }
    return false;
};

async function walk(width, port) {
    const [height, scale, touch] = SIZE[width] ?? [800, 1, false];
    const { page: cdp, close, errors } = await openChrome(port);
    const facts = { width, file: FILE.split(/[\\/]/).pop() };
    const shot = async (step) => {
        const { data } = await cdp('Page.captureScreenshot', { format: 'webp', quality: 80 });
        writeFileSync(join(OUT, `${NAME}-${width}-${step}.webp`), Buffer.from(data, 'base64'));
    };
    // a tap or a drag where a finger or the mouse would make it
    const tap = async (x, y) => {
        if (touch) { await cdp('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] }); await sleep(40); await cdp('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] }); } else {
            await cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y }); await cdp('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 }); await cdp('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', clickCount: 1 });
        }
        await sleep(300);
    };
    const drag = async (points) => {
        const [x0, y0] = points[0];
        if (touch) await cdp('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: x0, y: y0 }] });
        else { await cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x: x0, y: y0 }); await cdp('Input.dispatchMouseEvent', { type: 'mousePressed', x: x0, y: y0, button: 'left', clickCount: 1 }); }
        for (const [x, y] of points.slice(1)) {
            if (touch) await cdp('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x, y }] });
            else await cdp('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y, button: 'left', buttons: 1 });
            await sleep(16);
        }
        const [x1, y1] = points[points.length - 1];
        if (touch) await cdp('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        else await cdp('Input.dispatchMouseEvent', { type: 'mouseReleased', x: x1, y: y1, button: 'left', clickCount: 1 });
        await sleep(300);
    };
    const click = (selector) => evaluate(cdp, `(() => { const el = document.querySelector(${JSON.stringify(selector)}); if (!el) return false; el.scrollIntoView({ block: 'nearest' }); el.click(); return true; })()`);
    try {
        await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable'); await cdp('DOM.enable');
        await cdp('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: scale, mobile: touch });
        if (touch) await cdp('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
        await cdp('Network.setUserAgentOverride', { userAgent: touch ? UA.touch : UA.desk, acceptLanguage: LANG });
        await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
        await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
        await cdp('Page.navigate', { url: `${BASE}${LANG === 'cs' ? '' : `/${LANG}`}/tools/paint` });
        if (!await until(cdp, 'typeof window.mpPaint === "object"', 30000)) throw new Error('the page did not boot');
        await shot('1-empty');
        facts.hscroll_empty = await evaluate(cdp, 'document.documentElement.scrollWidth > innerWidth + 1');

        // the model goes into the page's own file field
        const { root } = await cdp('DOM.getDocument');
        const { nodeId } = await cdp('DOM.querySelector', { nodeId: root.nodeId, selector: '#paint-file' });
        const t0 = Date.now();
        await cdp('DOM.setFileInputFiles', { nodeId, files: [FILE] });
        if (!await until(cdp, 'window.mpPaint.mesh() !== null || /note-warn/.test(document.getElementById("paint-state").className) && !document.getElementById("paint-state").classList.contains("hidden") && window.mpPaint.mesh() === null && !/…/.test(document.getElementById("paint-state").textContent)', 300000)) throw new Error('the model did not open');
        facts.open_s = Math.round((Date.now() - t0) / 100) / 10;
        facts.state = await evaluate(cdp, 'document.getElementById("paint-state").textContent');
        if (!await evaluate(cdp, 'window.mpPaint.mesh() !== null')) { await shot('2-refused'); facts.refused = true; return facts; }
        await sleep(800);
        Object.assign(facts, await evaluate(cdp, `(() => { const s = window.mpPaint.state(); const gl = document.createElement('canvas').getContext('webgl2'); const ext = gl && gl.getExtension('WEBGL_debug_renderer_info');
            return { ...s.timings, brush_on: s.brushOn, phone: s.phone, renderer: ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : null, note: document.getElementById('paint-note').textContent }; })()`));
        // how long a frame takes with the model on the screen (the page's own loop draws every frame)
        facts.frame_ms = await evaluate(cdp, `new Promise((done) => { let n = 0; const t = performance.now(); const tick = () => { if (++n >= 30) done(Math.round((performance.now() - t) / n * 10) / 10); else requestAnimationFrame(tick); }; requestAnimationFrame(tick); })`);
        await evaluate(cdp, 'document.getElementById("tool-viewer").scrollIntoView({ block: "center" })');
        await sleep(200);
        // a place of the model's picture, in the window as it is scrolled now
        let box = [0, 0, 1, 1];
        const look = async () => { await evaluate(cdp, 'document.getElementById("tool-viewer").scrollIntoView({ block: "center" })'); await sleep(250); box = await evaluate(cdp, '(() => { const r = document.getElementById("tool-viewer").getBoundingClientRect(); return [r.left, r.top, r.width, r.height]; })()'); };
        const at = (fx, fy) => [Math.round(box[0] + box[2] * fx), Math.round(box[1] + box[3] * fy)];
        await look();
        await shot('2-model');

        if (CARD) {
            const pickTile = async (n) => { await click('#paint-swatches [data-add]'); await sleep(400); await evaluate(cdp, `(() => { const tiles = [...document.querySelectorAll("dialog[open] [data-pick]:not([disabled])")]; (tiles[${n}] ?? tiles[0])?.click(); })()`); await sleep(300); };
            const setRange = (id, value) => evaluate(cdp, `(() => { const el = document.getElementById(${JSON.stringify(id)}); el.value = ${JSON.stringify(String(value))}; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); })()`);
            const height = await evaluate(cdp, 'window.mpPaint.mesh().heightRange()[1] - window.mpPaint.mesh().heightRange()[0]');
            // closer to the model, and without the buttons that lie over the viewer
            await evaluate(cdp, '(() => { const v = window.mpPaint.view.host; v.camera.position.sub(v.controls.target).multiplyScalar(0.86).add(v.controls.target); v.controls.update(); document.getElementById("tool-viewbar").parentElement.style.display = "none"; })()');
            await sleep(300);
            // the second colour of the page from a third of the height, a third one from two thirds
            await click('#paint-band-add'); await sleep(400);
            await setRange('paint-band-0', Math.round(height * 0.34 * 5) / 5);
            await pickTile(1);
            await click('#paint-band-add'); await sleep(400);
            await setRange('paint-band-1', Math.round(height * 0.7 * 5) / 5);
            // a wavy line round the middle with the brush, in a fourth colour
            await pickTile(4);
            await click('#paint-bar [data-mode="brush"]');
            await setRange('paint-size', 3);
            await look();
            const wave = [];
            for (let i = 0; i <= 60; i++) wave.push(at(0.36 + 0.28 * i / 60, 0.5 + 0.035 * Math.sin(i / 60 * Math.PI * 6)));
            await drag(wave);
            await evaluate(cdp, 'document.getElementById("tool-viewer").dispatchEvent(new PointerEvent("pointerleave"))');
            await sleep(400);
            await look();
            const { data } = await cdp('Page.captureScreenshot', { format: 'png', clip: { x: box[0], y: box[1], width: box[2], height: box[3], scale: 2 } });
            writeFileSync(join(OUT, `${NAME}-card.png`), Buffer.from(data, 'base64'));
            facts.card = JSON.parse(await evaluate(cdp, 'JSON.stringify({ nT: window.mpPaint.mesh().nT, bands: window.mpPaint.state().ui.bands })'));
            return facts;
        }

        // a tap with the fill: the patch under the middle of the model
        const stats = 'JSON.stringify((() => { const m = window.mpPaint.mesh(); const a = m.areas(); let total = 0; for (const v of a) total += v; return { nT: m.nT, added: m.added(), shares: [...a].map((v) => Math.round(1000 * v / total) / 10).filter((v, i) => i < 8) }; })())';
        let t = Date.now();
        await tap(...at(0.5, 0.55));
        facts.fill_tap_ms = Date.now() - t - 300;
        facts.after_fill = JSON.parse(await evaluate(cdp, stats));
        await shot('3-fill');

        if (BRUSH && facts.brush_on) {
            await click('#paint-bar [data-mode="brush"]');
            // a second colour for the brush: the window of filaments, its third tile
            await click('#paint-swatches [data-add]');
            await sleep(400);
            await shot('4-colours');
            await evaluate(cdp, '(() => { const tiles = [...document.querySelectorAll("dialog[open] [data-pick]:not([disabled])")]; (tiles[2] ?? tiles[0])?.click(); })()');
            await sleep(300);
            await look();
            const points = [];
            for (let i = 0; i <= 24; i++) points.push(at(0.3 + 0.4 * i / 24, 0.42 + 0.06 * Math.sin(i / 24 * Math.PI * 2)));
            t = Date.now();
            await drag(points);
            facts.stroke_ms = Date.now() - t - 300 - 16 * 24;
            facts.after_brush = JSON.parse(await evaluate(cdp, stats));
            facts.sound = await evaluate(cdp, 'window.mpPaint.mesh().sound()');
            await shot('5-brush');
            // undo and redo of the stroke
            await click('#paint-undo');
            await sleep(200);
            facts.after_undo = JSON.parse(await evaluate(cdp, stats)).shares;
            await click('#paint-redo');
            await sleep(200);
        }

        // the fill took the whole body and there is no brush for a model this fine: the fill is taken back, so that the
        // height below has something to colour
        if (!(BRUSH && facts.brush_on) && facts.after_fill.shares[0] === 0) { await click('#paint-undo'); await sleep(600); }
        // a colour from a height up
        await click('#paint-band-add');
        await sleep(500);
        if (await evaluate(cdp, 'document.querySelector("dialog[open]") !== null')) { await evaluate(cdp, 'document.querySelector("dialog[open] [data-pick]:not([disabled])")?.click()'); await sleep(300); }
        facts.bands = await evaluate(cdp, 'window.mpPaint.state().ui.bands.length');
        await evaluate(cdp, 'document.getElementById("tool-viewer").scrollIntoView({ block: "center" })');
        await sleep(400);
        await shot('6-height');
        facts.hscroll = await evaluate(cdp, 'document.documentElement.scrollWidth > innerWidth + 1');
        facts.status = await evaluate(cdp, 'document.getElementById("tool-status").innerText');
        if (touch) { await evaluate(cdp, 'window.scrollTo(0, document.getElementById("sec-colors").getBoundingClientRect().top + scrollY - 60)'); await sleep(300); await shot('7-panel'); }

        if (SAVE) {
            t = Date.now();
            const saved = await evaluate(cdp, 'window.mpPaint.save().then((f) => f ? { uuid: f.uuid, status: f.status, colors: (f.colors ?? []).map((c) => c.hex), triangles: f.triangles, specks: f.paint?.report?.specks?.count ?? 0, kind: f.kind } : { failed: document.getElementById("tool-error").textContent })');
            facts.save_s = Math.round((Date.now() - t) / 100) / 10;
            facts.saved = saved;
            if (saved.uuid) {
                await cdp('Page.navigate', { url: `${BASE}${LANG === 'cs' ? '' : `/${LANG}`}/?open=${saved.uuid}` });
                await until(cdp, 'document.getElementById("viewer")?.dataset.painted === "colors" || document.getElementById("viewer")?.dataset.painted === "failed"', 90000);
                // the line of colours and the two prices come with the exact calculation
                await until(cdp, '!document.getElementById("calc-colors")?.classList.contains("hidden") && (document.getElementById("calc-colors-prices")?.innerText ?? "") !== ""', 60000);
                await sleep(500);
                facts.calc = await evaluate(cdp, `({ painted: document.getElementById('viewer')?.dataset.painted ?? null, line: document.getElementById('calc-colors-line')?.innerText ?? null, prices: document.getElementById('calc-colors-prices')?.innerText ?? null,
                    button: document.getElementById('cta-farm')?.innerText?.trim() ?? null, link: document.getElementById('paint-model-link')?.innerText?.trim() ?? null, linkShown: !document.getElementById('paint-model-link')?.classList.contains('hidden') })`);
                await shot('8-calculation');
                // back in the tool with the coloured model: its colours are there to go on with
                await cdp('Page.navigate', { url: `${BASE}${LANG === 'cs' ? '' : `/${LANG}`}/tools/paint?from=${saved.uuid}` });
                if (await until(cdp, 'typeof window.mpPaint === "object" && window.mpPaint.mesh() !== null', 60000)) {
                    await sleep(600);
                    facts.reopened = JSON.parse(await evaluate(cdp, 'JSON.stringify((() => { const m = window.mpPaint.mesh(); const a = m.areas(); let total = 0; for (const v of a) total += v; return { nT: m.nT, slots: window.mpPaint.state().ui.slots.filter((s) => s !== null), shares: [...a].slice(0, 6).map((v) => Math.round(1000 * v / total) / 10), note: document.getElementById("paint-note").textContent }; })())'));
                    await shot('9-reopened');
                }
            }
        }
        facts.errors = errors.slice(0, 5);
        return facts;
    } catch (e) {
        throw new Error(`${e.message}${errors.length ? ` | page errors: ${errors.slice(0, 5).join(' | ')}` : ''}`);
    } finally {
        await close();
    }
}

mkdirSync(OUT, { recursive: true });
const all = [];
let port = 9340 + Math.floor(Math.random() * 400);
for (const width of WIDTHS) {
    try {
        const facts = await walk(width, port++);
        all.push(facts);
        console.log(JSON.stringify(facts));
    } catch (e) {
        console.error(`${width}: ${e.message}`);
        process.exitCode = 1;
    }
}
const file = join(OUT, 'facts.json');
const before = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : {};
all.forEach((f) => { before[`${NAME}-${f.width}`] = f; });
writeFileSync(file, `${JSON.stringify(before, null, 2)}\n`);
