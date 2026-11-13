#!/usr/bin/env node
/**
 * The path "one press of Vytvořit ends on a design" (docs/AN.md §24) as a visitor sees it, on a phone (390 × 844) and
 * on a laptop (1280 × 800), with a faked assistant: headless Chrome over its DevTools protocol, no packages (the way
 * of scripts/review_round2.mjs).
 *
 * The machine this runs on may have no `intakes` table (migrations are not run in a worktree), so the answers of
 * POST /intake, of GET /intake/<token> and the hand-over of the tool's page are not asked from the local server: they
 * are read from a file that the real code wrote in a PHPUnit run with the faked assistant (--payloads=<file>, see
 * docs/AN.md §24.7; tests/fixtures/intake/direct_payloads.json) and put in the page's way: `fetch` is answered from the file, and the tool's page gets the
 * hand-over the server would have written into window.MP_TOOL. Everything else is the local server's own: the home
 * page, the tool's page and its preview, the generation (the fake generator: a cube) and the calculator.
 *
 *   node scripts/shots_home_direct.mjs --base=http://127.0.0.1:8017 --payloads=<file.json> --out=docs/img/home-direct [--widths=390,1280]
 */
import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, rmSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
    return hit ? hit.slice(hit.indexOf('=') + 1) : fallback;
};
const BASE = String(arg('base', 'http://127.0.0.1:8017')).replace(/\/$/, '');
const OUT = String(arg('out', 'docs/img/home-direct'));
const WIDTHS = String(arg('widths', '390,1280')).split(',').map(Number);
const ONLY = arg('only', null);
const PAYLOADS = JSON.parse(readFileSync(String(arg('payloads', 'payloads.json')), 'utf8'));
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const SIZE = { 390: [844, 2, true], 1280: [800, 1, false] };
const UA = {
    touch: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1 HeadlessChrome matplace-review',
    desk: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 HeadlessChrome matplace-review',
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
mkdirSync(OUT, { recursive: true });

async function openChrome(port) {
    const profile = mkdtempSync(join(tmpdir(), 'mp-direct-'));
    const proc = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', 'about:blank'], { stdio: 'ignore' });
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
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const page = (method, params) => send(method, params, sessionId);
    const close = async () => { try { await send('Browser.close'); } catch { /* gone */ } ws.close(); proc.kill(); await sleep(400); try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ } };
    return { page, close };
}

/** What stands in the page's way: the faked answers instead of the server's (see the head of this file). */
const inject = (scene) => `(() => {
    const S = ${JSON.stringify(scene)};
    window.__errs = [];
    window.addEventListener('error', (e) => window.__errs.push(String(e.message)));
    window.addEventListener('unhandledrejection', (e) => window.__errs.push('rejection: ' + String(e.reason && e.reason.message || e.reason)));
    const real = window.fetch.bind(window);
    const json = (body, status) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
    // (the home page's address has no slash before its query: "https://host?design=…")
    const local = (u) => { const x = new URL(String(u)); return location.origin + x.pathname + x.search; };
    window.fetch = async (u, o = {}) => {
        const url = new URL(String(u), location.href); const method = (o.method || 'GET').toUpperCase();
        if (method === 'POST' && /\\/intake$/.test(url.pathname)) {
            await new Promise((r) => setTimeout(r, 700));
            const a = JSON.parse(JSON.stringify(S.answer));
            if (a.go && a.route === 'tool') { sessionStorage.setItem('mpIntake', JSON.stringify(S.page)); a.go = local(a.go).replace(/intake=[A-Za-z0-9]+&?/, '').replace(/\\?$/, ''); }
            if (a.go && a.route === 'design') a.go = local(a.go).replace(/design=[a-z0-9]+/, 'design=' + S.gen);
            (a.others || []).forEach((x) => { x.url = local(x.url); });
            return json(a, 201);
        }
        if (method === 'GET' && /\\/intake\\/[A-Za-z0-9]{24}$/.test(url.pathname)) {
            const a = JSON.parse(JSON.stringify(S.again || {}));
            (a.others || []).forEach((x) => { x.url = local(x.url); });
            if (a.actions && a.actions.design) a.actions.design = local(a.actions.design).replace(/design=[a-z0-9]+/, 'design=' + S.gen);
            return json(a, 200);
        }
        if (method === 'GET' && S.hold && url.pathname.endsWith('/api/generate/' + S.gen)) {
            window.__holdUntil = window.__holdUntil || Date.now() + S.hold;
            if (Date.now() < window.__holdUntil) return json({ generation: { token: S.gen, status: 'running', progress: 42, error: null, target_mm: 80, file: null, options: null } }, 200);
        }
        return real(u, o);
    };
    let tool;
    Object.defineProperty(window, 'MP_TOOL', { configurable: true, get: () => tool, set: (x) => {
        const handed = sessionStorage.getItem('mpIntake');
        if (handed && x && location.pathname.includes('/tools/')) { x.intake = JSON.parse(handed); x.intake.back = local(x.intake.back); x.intake.file = null; }
        tool = x;
    } });
})();`;

const evaluate = async (cdp, expression) => (await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result.value;

async function run(name, scene, width, steps) {
    const [height, scale, touch] = SIZE[width];
    const { page: cdp, close } = await openChrome(9400 + Math.floor(Math.random() * 400));
    try {
        await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable');
        await cdp('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: scale, mobile: touch });
        if (touch) await cdp('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
        await cdp('Network.setUserAgentOverride', { userAgent: touch ? UA.touch : UA.desk, acceptLanguage: 'cs' });
        await cdp('Network.setExtraHTTPHeaders', { headers: { 'Accept-Language': 'cs' } });
        await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
        await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
        await cdp('Page.addScriptToEvaluateOnNewDocument', { source: inject(scene) });
        let n = 0;
        for (const [act, a, b] of steps) {
            if (act === 'go') { await cdp('Page.navigate', { url: BASE + a }); await sleep(3000); }
            if (act === 'wait') await sleep(a);
            if (act === 'type') await evaluate(cdp, `(() => { const el = document.querySelector(${JSON.stringify(a)}); el.focus(); el.value = ${JSON.stringify(b)}; el.dispatchEvent(new Event('input', { bubbles: true })); })()`);
            if (act === 'click') await evaluate(cdp, `(() => { const el = document.querySelector(${JSON.stringify(a)}); if (!el) return 'missing ${a}'; el.scrollIntoView({ block: 'center' }); el.click(); })()`);
            if (act === 'js') await evaluate(cdp, a);
            if (act === 'read') console.log(`  ${name}-${width}: ${a} = ${JSON.stringify(await evaluate(cdp, b))}`);
            if (act === 'shot') {
                n++;
                const file = `${name}-${width}-${n}${a ? `-${a}` : ''}.webp`;
                let shot;
                if (b === 'full') {
                    const h = await evaluate(cdp, 'Math.ceil(document.documentElement.scrollHeight)');
                    shot = await cdp('Page.captureScreenshot', { format: 'webp', quality: 60, captureBeyondViewport: true, clip: { x: 0, y: 0, width, height: Math.min(h, 6000), scale: touch ? 0.5 : 0.75 } });
                } else shot = await cdp('Page.captureScreenshot', { format: 'webp', quality: 78 });
                writeFileSync(join(OUT, file), Buffer.from(shot.data, 'base64'));
                console.log(`  ${file}`);
            }
        }
    } finally {
        await close();
    }
}

const STATE = "[location.pathname + location.search, document.getElementById('tool-intake')?.textContent ?? null, document.getElementById('intake-hint')?.textContent ?? null, document.getElementById('design-line')?.classList.contains('hidden') === false ? document.getElementById('design-line').innerText : null, document.getElementById('intake-assist')?.innerText ?? null, document.getElementById('status')?.textContent ?? null, document.documentElement.scrollWidth > innerWidth ? 'H-SCROLL' : 'no h-scroll', (window.__errs || []).join(' | ')]";

// a real generation of the local server (the fake generator; an identical wish is a free copy of the first result)
const generation = async (prompt) => {
    const res = await fetch(`${BASE}/api/generate`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ prompt, target_mm: 80 }) });
    const body = await res.json();
    if (!res.ok) throw new Error(`generate: ${res.status} ${JSON.stringify(body)}`);
    return body.generation.token;
};

const scenes = {
    // a clear tool: the sign opens with the name, the letter height, two colours and two pieces; then "I wanted something else"
    tool: async () => [{ answer: PAYLOADS.sign.answer, page: PAYLOADS.sign.page, again: PAYLOADS.sign.again }, [
        ['go', '/'], ['shot', 'home'], ['type', '#intake-text', PAYLOADS.sign.text], ['click', '#intake-submit'], ['wait', 300], ['shot', 'sending'], ['wait', 1200], ['read', 'after the press', STATE], ['wait', 9000],
        ['read', 'the tool', STATE], ['read', 'form', "JSON.stringify({ line1: document.querySelector('[data-text=line1]')?.value, height: document.querySelector('[data-param=text_height]')?.value, two: document.querySelector('[data-flag=two_color]')?.checked, qty: document.getElementById('param-qty')?.value })"],
        ['shot', 'tool'], ['shot', 'tool-full', 'full'], ['click', '#tool-intake-back'], ['wait', 3500], ['read', 'back home', STATE], ['read', 'field', "document.getElementById('intake-text').value"], ['shot', 'again'],
        ['click', '#intake-assist button.btn-secondary:last-of-type'], ['wait', 600], ['shot', 'again-request'],
    ]],
    // a medal for the second place: the tool's own preset, the inscription on its second line, the ribbon
    medal: async () => [{ answer: PAYLOADS.medal.answer, page: PAYLOADS.medal.page, again: PAYLOADS.medal.again }, [
        ['go', '/'], ['type', '#intake-text', PAYLOADS.medal.text], ['click', '#intake-submit'], ['wait', 11000], ['read', 'the tool', STATE],
        ['read', 'form', "JSON.stringify({ line1: document.querySelector('[data-text=line1]')?.value, line2: document.querySelector('[data-text=line2]')?.value, hang: document.querySelector('[data-choice=hang]:checked')?.value, width: document.querySelector('[data-param=width]')?.value })"], ['shot', 'tool'],
    ]],
    // no tool, the free design switched on: the calculator opens with the logo of dots, then with the design (the fake generator's cube)
    design: async () => {
        const gen = await generation('a headband with two devil horns');
        return [{ answer: PAYLOADS.design.answer, again: PAYLOADS.design.again, gen, hold: 9000 }, [
            ['go', '/'], ['type', '#intake-text', PAYLOADS.design.text], ['click', '#intake-submit'], ['wait', 300], ['shot', 'sending'], ['wait', 5200], ['read', 'waiting', STATE], ['shot', 'waiting'],
            ['wait', 22000], ['read', 'the design', STATE], ['read', 'refine', "document.getElementById('refine-box')?.classList.contains('hidden') ? 'refine hidden' : 'refine shown: ' + document.getElementById('refine-msg')?.textContent"],
            ['shot', 'design'], ['shot', 'design-full', 'full'], ['click', '#design-back'], ['wait', 3500], ['read', 'back home', STATE], ['shot', 'again'],
        ]];
    },
    // no tool, the free design off (today's default): one card, the request to a designer on it
    off: async () => [{ answer: PAYLOADS.off.answer }, [
        ['go', '/'], ['type', '#intake-text', PAYLOADS.off.text], ['click', '#intake-submit'], ['wait', 2500], ['read', 'the card', STATE], ['shot', 'card'],
    ]],
    // the free designs used up without an account: the sentence, "sign in", the tools that would also do
    limit: async () => [{ answer: PAYLOADS.limit.answer }, [
        ['go', '/'], ['type', '#intake-text', PAYLOADS.limit.text], ['click', '#intake-submit'], ['wait', 2500], ['read', 'the card', STATE], ['shot', 'card'],
    ]],
};

for (const [name, make] of Object.entries(scenes)) {
    if (ONLY && !String(ONLY).split(',').includes(name)) continue;
    for (const width of WIDTHS) {
        console.log(`${name} ${width}`);
        const [scene, steps] = await make();
        await run(name, scene, width, steps);
    }
}
