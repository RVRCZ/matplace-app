// The range by shape in the browser (resources/js/calc/rough.ts rangeFactors) against the reference vectors of
// tests/Unit/ShapeRangeTest.php, with the constants read from config/pricing.php: node scripts/check_rough_range.mjs
// (exits 1 when a number differs — then one of the two mirrors was changed without the other).
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const { build } = createRequire(join(root, 'package.json'))('esbuild');
const out = await build({ entryPoints: [join(root, 'resources/js/calc/rough.ts')], bundle: true, format: 'esm', write: false });
const mod = await import('data:text/javascript;base64,' + Buffer.from(out.outputFiles[0].text).toString('base64'));

// the constants as config/pricing.php has them under rough.range
const php = readFileSync(join(root, 'config/pricing.php'), 'utf8');
const block = /'range' => \[([\s\S]*?)\n {8}\],/.exec(php)?.[1] ?? '';
const edges = (k) => { const m = new RegExp(`'${k}' => \\[([\\d.]+), ([\\d.]+)\\]`).exec(block); return [Number(m[1]), Number(m[2])]; };
const terms = (k) => Object.fromEntries([...new RegExp(`'${k}' => \\[(.*)\\]`).exec(block)[1].matchAll(/'(\w+)' => ([\d.]+)/g)].map((m) => [m[1], Number(m[2])]));
const cfg = { height_mm: edges('height_mm'), fill_g_cm3: edges('fill_g_cm3'), small_minutes: edges('small_minutes'), overhang_share: edges('overhang_share'), triangles: edges('triangles'), time: terms('time'), price: terms('price') };

// [height, fill, overhang, supports, minutes, triangles] → factors, the same rows as ShapeRangeTest::vectors()
const vectors = [
    [[10, 0.5, 0, false, 12], { time: [0.39, 1.25], price: [0.49, 1.05] }],
    [[120, 0.03, 0, null, 200], { time: [0.78, 3.96], price: [0.7, 1.5939] }],
    [[57.5, 0.1, 0.5, null, 22], { time: [0.572, 2.8875], price: [0.588, 1.7364] }],
    [[50, 0.1, 0.1, true, 60], { time: [0.78, 2.8412], price: [0.7, 1.7218] }],
    [[null, null, null, null, 100], { time: [0.78, 3.96], price: [0.7, 1.5939] }],
    [[100, 0.046, 0.2, null, 103], { time: [0.78, 3.879], price: [0.7, 1.7905] }],
    [[40, 0.085, 0.05, null, 135, 1804554], { time: [1.248, 2.9581], price: [1.12, 1.4217] }],
    [[10, 0.5, 0, false, 100, 300000], { time: [0.8385, 1.25], price: [0.7525, 1.05] }],
];
let bad = 0;
for (const [[height, fill, overhang, supports, minutes, triangles], expected] of vectors) {
    const got = mod.rangeFactors(cfg, { height_mm: height, fill_g_cm3: fill, overhang_share: overhang, triangles: triangles ?? null }, supports, minutes);
    if (JSON.stringify(got) !== JSON.stringify(expected)) { bad++; console.error('WRONG', JSON.stringify([height, fill, overhang, supports, minutes, triangles]), JSON.stringify(got), 'expected', JSON.stringify(expected)); }
}
// the shape from what the page knows, and the price range landing on the rounding step
const shape = mod.shapeOf({ x: 150.39, y: 89.75, z: 100 }, 1, 62.7, 0.2, 2702740);
if (Math.abs(shape.fill_g_cm3 - 0.0464) > 0.001 || shape.height_mm !== 100 || shape.triangles !== 2702740) { bad++; console.error('WRONG shape', JSON.stringify(shape)); }
if (mod.shapeOf(null, 1, 1, null).triangles !== null) { bad++; console.error('WRONG shape without triangles'); }
const [lo, hi] = mod.range(10, [130], [0.7, 1.7905]);
if (lo !== 90 || hi !== 240) { bad++; console.error('WRONG range', lo, hi, 'expected 90 240'); }
const same = mod.range(10, [130, 170], null);
if (same[0] !== 130 || same[1] !== 170) { bad++; console.error('WRONG exact range', same); }
if (bad) { console.error(`${bad} wrong`); process.exit(1); }
console.log(`rough.ts range by shape: ${vectors.length} vectors agree with ShapeRangeTest`);
