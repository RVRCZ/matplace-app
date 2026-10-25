// The "Print" line of the order page in every state of an order (resources/js/calc/order_timeline.ts) and its price
// lines (order_price.ts), with the Czech words of lang/cs.json: node scripts/check_order_timeline.mjs (exits 1 when a
// line is not what it should be).
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const { build } = createRequire(join(root, 'package.json'))('esbuild');
const out = await build({ entryPoints: [join(root, 'resources/js/calc/order_timeline.ts')], bundle: true, format: 'esm', write: false });
const mod = await import('data:text/javascript;base64,' + Buffer.from(out.outputFiles[0].text).toString('base64'));
const cs = JSON.parse(readFileSync(join(root, 'lang/cs.json'), 'utf8'));
const plateOf = /'plate_of' => '([^']+)'/.exec(readFileSync(join(root, 'lang/cs/farm.php'), 'utf8'))?.[1] ?? 'podložka :i z :p';
const tr = (k, p = {}) => Object.entries(p).reduce((t, [a, b]) => t.replaceAll(':' + a, String(b)), k === 'farm.copies.plate_of' ? plateOf : cs[k] ?? k);
const duration = (min) => (min >= 60 ? `${Math.floor(min / 60)} h ${min % 60} min` : `${min} min`);
const base = { minutes: 90, plates: 1, plates_done: 0, plate_now: null, delivery: 'packeta_point', queue: null, print: null };
const plate = (i, p) => plateOf.replace(':i', i).replace(':p', p);

// state → [print line, dispatch line (null = the rule)]
const cases = [
    [{ ...base, status: 'sliced' }, ['1 h 30 min na tiskárně; začne, jakmile je tiskárna volná', null]],
    [{ ...base, status: 'queued', queue: { finish_in: 140 } }, ['1 h 30 min na tiskárně; začne, jakmile je tiskárna volná', null]],
    // F26-000088 (19 Oct 2026): printing, 8 %, plate 1 of 2 – it said "starts as soon as the printer is free"
    [{ ...base, status: 'printing', plates: 2, plate_now: 1, print: { progress: 8 }, queue: { finish_in: 83 } }, [`tiskne se, zbývá asi 1 h 23 min · ${plate(1, 2)}`, null]],
    [{ ...base, status: 'printing', print: { progress: 50 } }, ['tiskne se, zbývá asi 45 min', null]],
    [{ ...base, status: 'printing', print: { progress: 99.9 }, queue: { finish_in: 0 } }, ['tiskne se, dokončení každou chvíli', null]],
    [{ ...base, status: 'printing', minutes: null }, ['právě se tiskne', null]],
    [{ ...base, status: 'printing', plates: 2, plates_done: 1, print: { progress: 10 }, queue: { finish_in: 40 } }, [`tiskne se, zbývá asi 40 min · ${plate(2, 2)}`, null]],
    [{ ...base, status: 'done' }, ['dotištěno', null]],
    [{ ...base, status: 'handed_over' }, ['dotištěno', 'odesláno']],
    [{ ...base, status: 'handed_over', delivery: 'pickup' }, ['dotištěno', null]],
    [{ ...base, status: 'cancelled' }, [null, null]],
    [{ ...base, status: 'failed' }, [null, null]],
];
let bad = 0;
for (const [s, [print, dispatch]] of cases) {
    const got = [mod.timelinePrint(s, tr, duration), mod.timelineDispatch(s, tr)];
    if (got[0] !== print || got[1] !== dispatch) { bad++; console.error('WRONG', s.status, JSON.stringify(got), 'expected', JSON.stringify([print, dispatch])); }
}
// the price lines (resources/js/calc/order_price.ts): the print without delivery, the delivery line by the way chosen
const price = await import('data:text/javascript;base64,' + Buffer.from((await build({ entryPoints: [join(root, 'resources/js/calc/order_price.ts')], bundle: true, format: 'esm', write: false })).outputFiles[0].text).toString('base64'));
const money = (n) => `${n} Kč`;
const quote = { total: 259, shipping: 99 };
const priceCases = [
    [price.printOnly(quote), 160],
    [price.printOnly({ total: 160, shipping: 0 }), 160],
    [price.printOnly(null), null],
    [price.shippingText('packeta_point', quote, true, tr, money), 'Doprava Zásilkovna: 99 Kč'],
    [price.shippingText('packeta_home', { total: 309, shipping: 149 }, true, tr, money), 'Doprava Zásilkovna: 149 Kč'],
    [price.shippingText('packeta_point', null, true, tr, money), 'Doprava Zásilkovna: počítám…'],
    [price.shippingText('pickup', null, true, tr, money), 'Vyzvednutí: zdarma'],
    [price.shippingText('', null, true, tr, money), 'Doprava podle způsobu doručení níže'],
    [price.shippingText('', null, false, tr, money), ''],
    [price.shippingText('packeta_point', { total: 160, shipping: 0 }, false, tr, money), 'Doprava Zásilkovna: zdarma'],
    // the sum with the delivery under the price: exact from the quote, estimated from the list price, pickup = the print
    [price.totalText('packeta_point', 160, quote, 99, tr, money), 'Celkem k zaplacení 259 Kč'],
    [price.totalText('packeta_point', 160, null, 99, tr, money), 'Celkem asi 259 Kč, dopravu upřesní dopravce'],
    [price.totalText('pickup', 160, null, null, tr, money), 'Celkem k zaplacení 160 Kč'],
    [price.totalText('', 160, null, 99, tr, money), ''],
    [price.totalText('packeta_home', null, null, 149, tr, money), ''],
    [price.totalText('packeta_home', 160, null, null, tr, money), ''],
];
priceCases.forEach(([got, want], i) => { if (got !== want) { bad++; console.error('WRONG price line', i, JSON.stringify(got), 'expected', JSON.stringify(want)); } });
console.log(bad ? `${bad} lines wrong` : `order timeline ok: ${cases.length} states; price lines ok: ${priceCases.length}`);
process.exit(bad ? 1 : 0);
