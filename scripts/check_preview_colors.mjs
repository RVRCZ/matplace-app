/**
 * Does the preview of every tool page show the colours picked in the colour window?
 *
 * For each tool (and each of its variants with other parts: a box with a lid, a sign in two colours…) the script opens
 * the page as a new visitor and gives every colour control a test colour through the window "Barva" (the hex field, so
 * the free colour is what is tested). The test colour is the one of seven vivid hues the preview shows least of at that
 * moment; after the pick the preview has to show more of it. Nothing is stored: the page only asks for previews.
 *
 *   node scripts/check_preview_colors.mjs                      every tool on http://localhost:8000
 *   BASE=https://matplace.com node scripts/check_preview_colors.mjs sign-two beads 'box*'
 *
 * Needs puppeteer-core (not a dependency of the project: `npm i --no-save puppeteer-core`, or NODE_PATH to a folder
 * that has it) and Chrome (CHROME=path; the usual places are tried). SETTLE=ms waits longer after every change.
 * Exit code 1 when a picked colour is missing somewhere. One result is expected and is no failure: the pen of a
 * biscuit colours the next stroke, not the model. A modular set colours the bin that is selected in its grid.
 */
import { createRequire } from 'node:module';
import { existsSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const require = createRequire(import.meta.url);
let puppeteer;
try { puppeteer = require('puppeteer-core'); } catch { console.error('puppeteer-core is not installed: npm i --no-save puppeteer-core'); process.exit(2); }
const CHROME = process.env.CHROME ?? ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome', '/usr/bin/chromium', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'].find((p) => existsSync(p));
const BASE = (process.env.BASE ?? 'http://localhost:8000').replace(/\/$/, '');
const SETTLE = Number(process.env.SETTLE ?? 2500);
const SHOTS = process.env.SHOTS ?? join(tmpdir(), 'matplace-preview-colors');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
// vivid colours far from each other in hue: violet, pink, lime, yellow, teal, blue, orange. A sample picture may hold
// one of them already, so each pick takes the one the preview shows least of.
const TEST = ['#8a2be2', '#ff3fa4', '#7ac70c', '#e8c400', '#00b3a4', '#1e6bff', '#ff5a00'];
const MAX_CONTROLS = 6;
// a picked colour counts as seen when this much more of the preview has its hue (an eye of a face is a few hundred pixels)
const MORE = 0.0002;
// controls that do not recolour the model on show, by design
const EXPECTED_MISSING = { cookie: ['cookie-pen'] };

const flag = (name, on = true) => ({ flag: name, on });
const choice = (name, value) => ({ choice: name, value });
const click = (selector) => ({ click: selector });
const V = (name, url, setup = [], extra = {}) => ({ name, url, setup, ...extra });
const VARIANTS = [
    V('organizer', '/tools/organizer'), V('modular', '/tools/modular-organizer', [click('[role="gridcell"][aria-label*="×"]')]),
    V('box', '/tools/box'), V('box-lid', '/tools/box', [flag('lid')]),
    V('phone_stand', '/tools/phone-stand'), V('holder', '/tools/holder'), V('cap', '/tools/cap'), V('cable_holder', '/tools/cable-holder'),
    V('vase', '/tools/vase'), V('vase-pot-saucer', '/tools/vase', [choice('purpose', 'pot'), flag('saucer')]),
    V('sign', '/tools/sign'), V('sign-two', '/tools/sign', [flag('two_color')]), V('sign-outline-two', '/tools/sign', [choice('style', 'outline'), flag('two_color')]),
    V('sign-name-two', '/tools/sign', [choice('style', 'name'), flag('two_color')]), V('sign-engrave', '/tools/sign', [choice('style', 'engrave')]), V('sign-stand', '/tools/sign', [choice('style', 'stand')]),
    V('nameplate', '/tools/nameplate'), V('nameplate-form', '/tools/nameplate?form=1'), V('text', '/tools/text'),
    V('qr', '/tools/qr'), V('qr-stand', '/tools/qr', [flag('stand')]),
    V('logo', '/tools/logo'), V('logo-standing', '/tools/logo', [choice('mode', 'standing')]), V('svg_to_stl', '/tools/svg-to-stl'),
    V('cutter', '/tools/cookie-cutter'), V('stamp', '/tools/stamp'), V('stamp-knob', '/tools/stamp', [choice('handle', 'knob')]),
    V('papel', '/tools/papel-picado'), V('papel-portrait', '/tools/papel-picado', [choice('treatment', 'portrait')], { wait: 9000 }),
    V('notes', '/tools/sticky-notes'), V('hair_tie', '/tools/hair-tie-holder'), V('candle_stand', '/tools/candle-stand'), V('stencil', '/tools/stencil'),
    V('lightbox', '/tools/illuminated-sign'), V('beads', '/tools/letter-beads'), V('name_cup', '/tools/name-organizer'),
    V('ornament', '/tools/ornament'), V('gingerbread', '/tools/gingerbread'), V('cookie', '/tools/cookie'), V('topper', '/tools/cake-topper'),
    V('tray', '/tools/shape-tray'), V('name_letter', '/tools/name-letter'), V('charm', '/tools/charm'),
    V('keychain', '/tools/keychain'), V('keychain-form', '/tools/keychain?form=1'), V('earrings', '/tools/earrings'), V('magnet', '/tools/magnet'),
    V('badge', '/tools/badge-reel'), V('medallion', '/tools/medallion'), V('photo_organizer', '/tools/photo-organizer'), V('bag_charm', '/tools/bag-charm'),
    V('compose', '/tools/compose'), V('coaster', '/tools/coaster'), V('filament_art', '/tools/filament-art', [], { wait: 7000 }),
    V('filament_art-stack', '/tools/filament-art', [choice('mode', 'stack')], { wait: 7000 }),
];

/** The share of a picture's pixels that are clearly of the hue of `hex` (shaded or lit, but coloured). Runs in the page. */
const hueShare = async (page, png, hex) => page.evaluate(async (data, hex) => {
    const img = new Image();
    await new Promise((done, fail) => { img.onload = done; img.onerror = fail; img.src = `data:image/png;base64,${data}`; });
    const c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
    const g = c.getContext('2d'); g.drawImage(img, 0, 0);
    const px = g.getImageData(0, 0, c.width, c.height).data;
    const hue = (r, gg, b) => { const mx = Math.max(r, gg, b); const d = mx - Math.min(r, gg, b); if (d < 1e-9) return [0, 0, mx]; const h = mx === r ? ((gg - b) / d + 6) % 6 : mx === gg ? (b - r) / d + 2 : (r - gg) / d + 4; return [h * 60, d / mx, mx]; };
    const want = hue(parseInt(hex.slice(1, 3), 16) / 255, parseInt(hex.slice(3, 5), 16) / 255, parseInt(hex.slice(5, 7), 16) / 255)[0];
    let hit = 0;
    for (let i = 0; i < px.length; i += 4) {
        const [h, s, v] = hue(px[i] / 255, px[i + 1] / 255, px[i + 2] / 255);
        if (s > 0.3 && v > 0.18 && Math.abs(((h - want + 540) % 360) - 180) < 16) hit++;
    }
    return hit / (px.length / 4);
}, png, hex);

const wanted = process.argv.slice(2);
const chosen = VARIANTS.filter((v) => !wanted.length || wanted.some((w) => v.name === w || (w.endsWith('*') && v.name.startsWith(w.slice(0, -1)))));
if (!CHROME) { console.error('Chrome was not found: set CHROME to its path'); process.exit(2); }
mkdirSync(SHOTS, { recursive: true });
const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--window-size=1400,1000'] });
let bad = 0;
for (const v of chosen) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1400, height: 1000 });
    const errors = []; const lines = [];
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
    page.on('response', (res) => { if (res.status() >= 400 && !res.url().includes('favicon')) errors.push(`http ${res.status()} ${res.url().replace(BASE, '')}`); });
    const settle = v.wait ?? SETTLE;
    let state = 'OK';
    try {
        // a fresh visitor: no stored settings, no recent colours
        await page.evaluateOnNewDocument(() => { try { localStorage.clear(); } catch { /* private mode */ } });
        await page.goto(BASE + v.url, { waitUntil: 'networkidle2', timeout: 90000 });
        if (!(await page.$('#tool-viewer'))) throw new Error('no tool page here');
        await page.waitForFunction(() => document.getElementById('tool-empty')?.classList.contains('hidden'), { timeout: 60000 });
        for (const s of v.setup) {
            const done = await page.evaluate((s) => {
                if (s.click) { const el = document.querySelector(s.click); if (!el) return false; el.click(); return true; }
                if (s.flag) { const el = document.querySelector(`[data-flag="${s.flag}"]`); if (!el) return false; if (el.checked !== s.on) el.click(); return true; }
                const radio = document.querySelector(`input[data-choice="${s.choice}"][value="${s.value}"]`);
                if (radio) { radio.click(); return true; }
                const sel = document.querySelector(`select[data-choice="${s.choice}"]`);
                if (sel) { sel.value = s.value; sel.dispatchEvent(new Event('input', { bubbles: true })); sel.dispatchEvent(new Event('change', { bubbles: true })); return true; }
                return false;
            }, s);
            if (!done) errors.push(`setup not found: ${JSON.stringify(s)}`);
            await sleep(settle);
        }
        await sleep(settle);
        const swatches = async () => { const all = await page.$$('.tool-swatch'); const seen = []; for (const h of all) if (await h.evaluate((e) => !e.closest('dialog') && e.offsetParent !== null)) seen.push(h); return seen; };
        const shot = async (name) => (await page.$('#tool-viewer')).screenshot({ encoding: 'base64', ...(name ? { path: join(SHOTS, `${v.name}_${name}.png`) } : {}) });
        let before = await shot('before');
        const allowed = EXPECTED_MISSING[v.name] ?? [];
        const left = [...TEST];
        const count = Math.min((await swatches()).length, MAX_CONTROLS);
        if (!count) state = '–';
        for (let i = 0; i < count; i++) {
            const control = (await swatches())[i];
            if (!control) { errors.push(`control ${i} is gone`); break; }
            const label = await control.evaluate((e) => e.id || e.closest('[data-part]')?.dataset.part || e.dataset.swatchFor || (e.getAttribute('aria-label') ?? '?').split(':')[0]);
            // the test colour the preview shows least of right now
            const shares = [];
            for (const hex of left) shares.push([await hueShare(page, before, hex), hex]);
            const [was, hex] = shares.sort((a, b) => a[0] - b[0])[0];
            left.splice(left.indexOf(hex), 1);
            await control.evaluate((e) => e.scrollIntoView({ block: 'center' }));
            await control.click();
            if (!(await page.waitForSelector('dialog.tool-dialog-color[open]', { timeout: 5000 }).catch(() => null))) { errors.push(`no colour window for ${label}`); continue; }
            await page.focus('dialog.tool-dialog-color [data-hex]');
            await page.keyboard.type(hex);
            await page.click('dialog.tool-dialog-color [data-use]');
            await sleep(settle);
            before = await shot(i === count - 1 ? 'after' : null);
            const now = await hueShare(page, before, hex);
            const seen = now >= was + MORE && now >= 2 * was;
            const expected = !seen && (allowed.includes('*') || allowed.includes(label));
            if (!seen && !expected) state = 'BAD';
            lines.push(`${label} ${hex} ${seen ? 'seen' : expected ? 'not seen (as expected)' : 'MISSING'} (${(was * 100).toFixed(2)} → ${(now * 100).toFixed(2)} %)`);
        }
    } catch (e) { errors.push(String(e.message).slice(0, 200)); }
    if (errors.length) state = 'BAD';
    if (state === 'BAD') bad++;
    console.log(`${v.name.padEnd(20)} ${state.padEnd(3)} ${lines.join(' · ')}${errors.length ? ` · ${errors.join(' | ').slice(0, 300)}` : ''}`);
    await page.close();
}
await browser.close();
console.log(`${chosen.length} pages, ${bad} bad; pictures in ${SHOTS}`);
process.exit(bad ? 1 : 0);
