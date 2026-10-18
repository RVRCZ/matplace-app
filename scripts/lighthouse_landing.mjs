/**
 * Lighthouse (performance only) on the landing pages, mobile and desktop (docs/AH.md §11):
 *   node scripts/lighthouse_landing.mjs [base=https://matplace.com] [label=prod] [pages=/,/tools/figure,…]
 * Lighthouse is not a dependency of the repo: it runs from npx (lighthouse@12.6.0) or from LIGHTHOUSE (path to its
 * cli/index.js, e.g. installed in a scratch folder). Our cookie mp_test=1 keeps the runs out of the statistics.
 * Writes <out>/<label>-<form>-<page>.json and results-<label>.json in the current folder, a line per run on the console.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const base = (process.argv[2] ?? 'https://matplace.com').replace(/\/$/, '');
const label = process.argv[3] ?? 'prod';
const pages = (process.argv[4] ?? '/,/tools/figure,/tools/pet-figurine,/tools/qr,/tools/organizer,/tools/box').split(',');
const chrome = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
mkdirSync('out', { recursive: true });
const headers = join(tmpdir(), 'mp-lighthouse-headers.json');
writeFileSync(headers, JSON.stringify({ Cookie: 'mp_test=1' }));
const cli = process.env.LIGHTHOUSE ? [process.execPath, [process.env.LIGHTHOUSE]] : [process.platform === 'win32' ? 'npx.cmd' : 'npx', ['-y', 'lighthouse@12.6.0']];
const rows = [];
for (const page of pages) {
    for (const form of ['mobile', 'desktop']) {
        const file = `out/${label}-${form}-${page.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home'}.json`;
        const args = [...cli[1], base + page, '--quiet', '--output=json', `--output-path=${file}`, '--only-categories=performance',
            `--chrome-path=${chrome}`, '--chrome-flags=--headless=new --no-first-run', `--extra-headers=${headers}`];
        if (form === 'desktop') args.push('--preset=desktop');
        try { execFileSync(cli[0], args, { stdio: 'ignore', timeout: 180000, shell: cli[0].endsWith('.cmd') }); } catch (e) { if (!existsSync(file)) { rows.push({ page, form, error: String(e.message).slice(0, 80) }); continue; } }
        const r = JSON.parse(readFileSync(file, 'utf8'));
        const a = r.audits;
        const items = a['resource-summary']?.details?.items ?? [];
        const kb = (t) => Math.round((items.find((i) => i.resourceType === t)?.transferSize ?? 0) / 1024);
        const img = (a['total-byte-weight']?.details?.items ?? []).filter((i) => /\.(webp|jpe?g|png|avif|gif)(\?|$)/i.test(i.url)).sort((x, y) => y.totalBytes - x.totalBytes)[0];
        const lcpEl = a['largest-contentful-paint-element']?.details?.items?.[0]?.items?.[0]?.node?.snippet ?? '';
        rows.push({
            page, form, score: Math.round((r.categories.performance.score ?? 0) * 100),
            lcp: +(a['largest-contentful-paint'].numericValue / 1000).toFixed(2), fcp: +(a['first-contentful-paint'].numericValue / 1000).toFixed(2),
            cls: +a['cumulative-layout-shift'].numericValue.toFixed(3), tbt: Math.round(a['total-blocking-time'].numericValue),
            si: +(a['speed-index'].numericValue / 1000).toFixed(2), jsKB: kb('script'), cssKB: kb('stylesheet'), imgKB: kb('image'), fontKB: kb('font'), totalKB: kb('total'),
            bigImg: img ? `${img.url.replace(base, '').slice(0, 60)} ${Math.round(img.totalBytes / 1024)} kB` : '', lcpEl: lcpEl.slice(0, 90),
        });
        console.log(JSON.stringify(rows.at(-1)));
    }
}
writeFileSync(`results-${label}.json`, JSON.stringify(rows, null, 2));
