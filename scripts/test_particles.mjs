/**
 * Test of the dots of the logo (resources/js/calc/particles_points.ts) without a browser: a small picture drawn here
 * and the real public/img/logo.png, decoded by the few lines below (8-bit RGBA PNG, as the logo is).
 *
 *   node scripts/test_particles.mjs          exit code 1 when a check fails
 *
 * Node 22.18+ reads the TypeScript module directly (type stripping).
 */
import { readFileSync } from 'node:fs';
import { inflateSync } from 'node:zlib';
import { samplePoints, partOf } from '../resources/js/calc/particles_points.ts';

let failed = 0;
const check = (ok, what) => { console.log(`${ok ? 'ok  ' : 'FAIL'} ${what}`); if (!ok) failed++; };

/** An 8-bit RGBA, non-interlaced PNG → {width, height, data}. */
function readPng(path) {
    const b = readFileSync(path);
    let at = 8; let width = 0; let height = 0; const idat = [];
    while (at < b.length) {
        const len = b.readUInt32BE(at); const type = b.toString('ascii', at + 4, at + 8); const body = b.subarray(at + 8, at + 8 + len);
        if (type === 'IHDR') {
            width = body.readUInt32BE(0); height = body.readUInt32BE(4);
            if (body[8] !== 8 || body[9] !== 6 || body[12] !== 0) throw new Error('only 8-bit RGBA, not interlaced');
        }
        if (type === 'IDAT') idat.push(body);
        at += 12 + len;
    }
    const raw = inflateSync(Buffer.concat(idat)); const stride = width * 4; const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        const f = raw[y * (stride + 1)]; const row = raw.subarray(y * (stride + 1) + 1, (y + 1) * (stride + 1)); const o = y * stride;
        for (let x = 0; x < stride; x++) {
            const a = x >= 4 ? data[o + x - 4] : 0; const up = y ? data[o - stride + x] : 0; const ul = y && x >= 4 ? data[o - stride + x - 4] : 0;
            const p = a + up - ul; const pa = Math.abs(p - a); const pb = Math.abs(p - up); const pc = Math.abs(p - ul);
            const pred = [0, a, up, (a + up) >> 1, pa <= pb && pa <= pc ? a : pb <= pc ? up : ul][f];
            data[o + x] = (row[x] + pred) & 255;
        }
    }
    return { width, height, data };
}

// ── a small picture: a teal block with a dark outline and an orange thread down its left side, on nothing ─────────
const W = 60; const H = 40; const small = new Uint8ClampedArray(W * H * 4);
const paint = (x, y, rgb) => { const o = (y * W + x) * 4; small[o] = rgb[0]; small[o + 1] = rgb[1]; small[o + 2] = rgb[2]; small[o + 3] = 255; };
for (let y = 5; y < 35; y++) for (let x = 20; x < 55; x++) paint(x, y, x < 22 || x > 52 || y < 7 || y > 32 ? [0, 71, 58] : [46, 168, 152]);
for (let y = 2; y < 38; y++) for (let x = 8; x < 11; x++) paint(x, y, [197, 90, 62]);
const dots = samplePoints(small, W, H, 200);
check(dots.length <= 200 && dots.length >= 170, `about 200 dots asked, ${dots.length} given, never more`);
check(dots.every((d) => small[(Math.floor(d.y * H) * W + Math.floor(d.x * W)) * 4 + 3] === 255), 'every dot lies on the picture, none on its transparent ground');
check(dots.every((d) => d.x > 0 && d.x < 1 && d.y > 0 && d.y < 1), 'places are shares of the width and the height');
check(dots.every((d) => d.part === partOf(...d.rgb)), 'each dot is of the part its colour says');
const share = (part) => dots.filter((d) => d.part === part).length / dots.length;
const area = { filament: 3 * 36, line: 0, fill: 0 };
for (let y = 5; y < 35; y++) for (let x = 20; x < 55; x++) (x < 22 || x > 52 || y < 7 || y > 32 ? area.line++ : area.fill++);
const total = area.filament + area.line + area.fill;
check(share('filament') > area.filament / total, `the thin thread gets more dots than its area (${(share('filament') * 100).toFixed(1)} % of dots, ${(area.filament / total * 100).toFixed(1)} % of the area)`);
const thread = dots.filter((d) => d.part === 'filament').sort((a, b) => a.y - b.y);
check(thread.length > 0 && thread[0].along === 0 && thread[thread.length - 1].along === 1 && thread.every((d, i) => !i || d.along >= thread[i - 1].along), 'along the thread runs from 0 at its top to 1 at its end');
check(dots.filter((d) => d.part !== 'filament').every((d) => d.along === 0), 'only the thread has a place along it');
check(JSON.stringify(samplePoints(small, W, H, 200)) === JSON.stringify(dots), 'the same picture gives the same dots');
check(samplePoints(small, W, H, 5000).length <= W * H, 'never more dots than the picture has places for');
check(samplePoints(new Uint8ClampedArray(W * H * 4), W, H, 100).length === 0, 'a picture with nothing on it gives no dots');

// ── the real logo ─────────────────────────────────────────────────────────────────────────────────────────────────
const logo = readPng(new URL('../public/img/logo.png', import.meta.url));
const t0 = performance.now();
const real = samplePoints(logo.data, logo.width, logo.height, 3000);
const few = samplePoints(logo.data, logo.width, logo.height, 1500);
const ms = performance.now() - t0;
check(real.length <= 3000 && real.length >= 2700, `the logo gives about 3000 dots (${real.length}) in ${ms.toFixed(0)} ms`);
check(few.length <= 1500 && few.length >= 1350, `and about 1500 when a phone asks for fewer (${few.length})`);
const parts = Object.fromEntries(['fill', 'line', 'filament'].map((p) => [p, real.filter((d) => d.part === p).length]));
check(parts.fill > 900 && parts.line > 600 && parts.filament > 100, `teal, outline and thread all present: ${JSON.stringify(parts)}`);
const xs = real.map((d) => d.x); const ys = real.map((d) => d.y);
check(Math.min(...xs) < 0.05 && Math.max(...xs) > 0.95 && Math.min(...ys) < 0.05 && Math.max(...ys) > 0.95, 'the dots reach all four edges of the logo');
const orange = real.filter((d) => d.part === 'filament');
check(orange.every((d) => d.x < 0.35 && d.y < 0.65), 'the thread is where it is drawn: from the nozzle down to the M');
process.exit(failed ? 1 : 0);
