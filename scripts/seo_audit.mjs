#!/usr/bin/env node
/**
 * Every page search engines are meant to read, measured the way a search engine and a phone see it (docs/AP.md §15).
 *
 *   node scripts/seo_audit.mjs --base=http://127.0.0.1:8097 --out=storage/app/seo-audit/local
 *   node scripts/seo_audit.mjs --base=https://matplace.com  --out=storage/app/seo-audit/prod --browser=important
 *
 * What it reads: the sitemap index of --base and every map in it, plus the entry pages in IMPORTANT below (in the
 * three languages). Addresses in a map that name another host (a local server whose APP_URL is not --base) are read
 * on --base. For each page:
 *   status and the chain of redirects, canonical, robots (meta and X-Robots-Tag), hreflang (cs/en/es + x-default,
 *   reciprocity), title and description (length, unique within the language), the H1s and the outline of headings,
 *   words of the page's own text (inside <main>, without scripts, menus and form lists), the share of that text
 *   standing on five or more other pages of the same language (template text), pictures (alt, width/height, format,
 *   lazy, weight), internal links out and in (pages nobody links to, clicks from the home page), structured data
 *   (kinds, valid JSON-LD, questions of FAQPage and prices of Product present in the visible text), Open Graph,
 *   the weight of the HTML, CSS and JS, and with --browser a headless Chrome of 390 × 844: LCP, CLS, requests, bytes.
 *
 * Gentle by default: one request at a time, at most --rate per second (2), marked as test traffic (cookie mp_test=1
 * and the X-Matplace-Test header, docs/AJ.md), so a run adds no visits to the statistics. A page opened in Chrome asks
 * for its files at once; the pause after it is as long as those requests would take at --rate.
 *
 * Options: --browser[=all|important|N]  measure in Chrome (important = IMPORTANT + the campaign's tools; N = the first N)
 *          --only=<regex>              audit only addresses whose path matches
 *          --links=0                   skip opening every internal link (redirects, broken links)
 *          --images=0                  skip weighing pictures
 *          --rate=2                    requests per second
 *          --compare=<before>,<after>  no reading: the two summary.json files (or their folders) side by side as a table
 *
 * Output in --out: pages.json (everything), pages.csv (one row per page), summary.json and summary.md (the numbers
 * for a before/after table), findings.md (what is wrong, the pages of the campaign first). No packages.
 */
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, rmSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a === `--${name}` || a.startsWith(`--${name}=`));
    if (!hit) return fallback;
    return hit.includes('=') ? hit.slice(hit.indexOf('=') + 1) : true;
};
const BASE = String(arg('base', 'http://127.0.0.1:8097')).replace(/\/$/, '');
const OUT = String(arg('out', 'storage/app/seo-audit/run'));
const RATE = Number(arg('rate', 2)) || 2;
const ONLY = arg('only', null) ? new RegExp(String(arg('only'))) : null;
const LINKS = String(arg('links', '1')) !== '0';
const IMAGES = String(arg('images', '1')) !== '0';
const BROWSER = arg('browser', null);
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const UA = 'Mozilla/5.0 (compatible; matplace-seo-audit; mp_test)';
const LANGS = ['cs', 'en', 'es'];

/** Entry pages that matter whether or not a map lists them (the brief of 13 Nov 2026). */
const IMPORTANT = ['/', '/tools', '/gifts', '/3d-tisk-praha', '/business', '/farm', '/models', '/blog'];
/** What the Christmas campaign leads to and what Google did not know on 13 Nov 2026: these come first in findings. */
const CAMPAIGN = ['/tools/figure', '/tools/pet-figurine', '/tools/ornament', '/tools/gingerbread', '/tools/medallion', '/tools/keychain', '/tools/charm', '/tools/organizer',
    '/gifts', '/tools', '/3d-tisk-praha', '/business', '/'];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let last = 0;
/** One request at a time, spaced so that the server sees at most RATE a second. */
async function polite(url, opts = {}) {
    const wait = last + 1000 / RATE - Date.now();
    if (wait > 0) await sleep(wait);
    last = Date.now();
    const headers = { 'User-Agent': UA, Cookie: 'mp_test=1', 'X-Matplace-Test': '1', 'Accept-Language': 'cs', ...(opts.headers ?? {}) };
    for (let attempt = 0; ; attempt++) {
        try {
            return await fetch(url, { redirect: 'manual', ...opts, headers, signal: AbortSignal.timeout(60000) });
        } catch (e) {
            if (attempt >= 2) return { status: 0, headers: new Headers(), text: async () => '', arrayBuffer: async () => new ArrayBuffer(0), error: String(e) };
            await sleep(1500);
        }
    }
}

const onBase = (url) => {
    // a map written by a server with another APP_URL still names pages of the server being audited
    const u = new URL(url, BASE + '/');
    return BASE + u.pathname + u.search;
};
const pathOf = (url) => { const u = new URL(url, BASE + '/'); return (u.pathname.replace(/\/$/, '') || '/') + u.search; };
const langOf = (path) => (path.match(/^\/(en|es)(\/|$)/)?.[1] ?? 'cs');
const bare = (path) => path.replace(/^\/(en|es)(?=\/|$)/, '') || '/';
const localized = (path, lang) => (lang === 'cs' ? path : `/${lang}${path === '/' ? '' : path}`);

/** Follows redirects by hand so the chain is known. */
async function get(url) {
    const chain = [];
    let at = url;
    for (let i = 0; i < 8; i++) {
        const res = await polite(at);
        if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
            const to = new URL(res.headers.get('location'), at).href;
            chain.push({ from: at, status: res.status, to });
            at = to;
            if (!at.startsWith(BASE)) return { status: res.status, chain, url: at, html: '', headers: res.headers, external: true };
            continue;
        }
        const html = res.status === 200 ? await res.text() : '';
        return { status: res.status, chain, url: at, html, headers: res.headers };
    }
    return { status: 0, chain, url: at, html: '', headers: new Headers(), loop: true };
}

// ---------------------------------------------------------------------------------------------------------------- HTML

const ENT = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', middot: '·', ndash: '–', mdash: '—', hellip: '…', times: '×', rarr: '→', larr: '←', copy: '©', euro: '€' };
const decode = (s) => String(s ?? '').replace(/&(#x?[0-9a-f]+|[a-z]+);/gi, (m, e) => {
    if (e[0] === '#') { const n = e[1].toLowerCase() === 'x' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10); return Number.isFinite(n) ? String.fromCodePoint(n) : m; }
    return ENT[e.toLowerCase()] ?? m;
});
const clean = (s) => decode(s).replace(/\s+/g, ' ').trim();
const attrs = (raw) => {
    const out = {};
    for (const m of raw.matchAll(/([^\s"'=<>\/]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+)))?/g)) out[m[1].toLowerCase()] = m[2] ?? m[3] ?? m[4] ?? '';
    return out;
};
const words = (s) => (s.match(/[\p{L}\p{N}][\p{L}\p{N}'’.,-]*/gu) ?? []).length;
const norm = (s) => s.toLowerCase().replace(/[\s\u00a0]+/g, ' ').replace(/[“”„"'’]/g, '').trim();

const RAW = new Set(['script', 'style', 'textarea', 'title']);
const SKIP = new Set(['script', 'style', 'svg', 'template', 'noscript', 'select', 'nav', 'header', 'footer', 'dialog', 'textarea', 'datalist']);
const BLOCK = new Set(['p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'section', 'article', 'td', 'th', 'tr', 'summary', 'details', 'figcaption', 'figure',
    'label', 'button', 'dt', 'dd', 'ul', 'ol', 'table', 'form', 'main', 'blockquote', 'br', 'fieldset', 'legend', 'aside', 'pre']);
const VOID = new Set(['br', 'img', 'input', 'meta', 'link', 'hr', 'source', 'area', 'base', 'col', 'embed', 'track', 'wbr']);

/** One pass over the tags: the head's facts, and the visible text of <main> as blocks. */
function parse(html, pageUrl) {
    const page = { title: null, titles: 0, description: null, descriptions: 0, canonical: null, robots: null, hreflang: {}, og: {}, lang: null, h1: [], outline: [],
        images: [], links: [], jsonld: [], css: [], js: [], preloads: [], blocks: [], bodyText: '', breadcrumbNav: false, viewport: null };
    const re = /<!--[\s\S]*?-->|<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|'[^']*'|[^'">])*)>/g;
    let m; let pos = 0; let inMain = 0; let skip = 0; const skipStack = []; let block = ''; let heading = null; let all = [];
    let anchor = null; let picture = null;
    const flush = () => { const t = clean(block); block = ''; if (t) page.blocks.push(t); };
    const text = (t) => {
        if (!t) return;
        if (heading) heading.text += t;
        if (anchor) anchor.text += t;
        if (skip) return;
        all.push(t);
        if (inMain) block += t;
    };
    while ((m = re.exec(html))) {
        text(html.slice(pos, m.index));
        pos = re.lastIndex;
        if (m[0].startsWith('<!--')) continue;
        const closing = m[1] === '/'; const tag = m[2].toLowerCase(); const a = closing ? {} : attrs(m[3]);
        if (!closing && RAW.has(tag)) {
            // the content of a raw element runs to its own closing tag, whatever stands inside
            const end = html.toLowerCase().indexOf(`</${tag}`, pos);
            const inner = end < 0 ? '' : html.slice(pos, end);
            const after = end < 0 ? html.length : html.indexOf('>', end) + 1;
            if (tag === 'title' && !skip) { page.titles++; page.title ??= clean(inner); }
            if (tag === 'script' && /ld\+json/i.test(a.type ?? '')) page.jsonld.push(inner);
            if (tag === 'script' && a.src) page.js.push(new URL(decode(a.src), pageUrl).href);
            pos = re.lastIndex = after;
            continue;
        }
        if (closing) {
            if (skipStack.length && skipStack[skipStack.length - 1] === tag) { skipStack.pop(); skip--; }
            if (tag === 'main') { flush(); inMain = Math.max(0, inMain - 1); }
            if (heading && tag === heading.tag) { const t = clean(heading.text); page.outline.push({ level: Number(tag[1]), text: t }); if (tag === 'h1') page.h1.push(t); heading = null; }
            if (tag === 'picture') picture = null;
            if (anchor && tag === 'a') { anchor.text = clean(anchor.text); page.links.push(anchor); anchor = null; }
            if (BLOCK.has(tag)) { flush(); all.push(' '); }
            continue;
        }
        if (tag === 'html') page.lang = a.lang ?? null;
        if (tag === 'meta') {
            const name = (a.name ?? a.property ?? '').toLowerCase();
            if (name === 'description') { page.descriptions++; page.description ??= clean(a.content); }
            if (name === 'robots') page.robots = (a.content ?? '').toLowerCase();
            if (name === 'viewport') page.viewport = a.content;
            if (name.startsWith('og:') || name.startsWith('twitter:')) page.og[name] = decode(a.content ?? '');
        }
        if (tag === 'link') {
            const rel = (a.rel ?? '').toLowerCase();
            if (rel === 'canonical') page.canonical = decode(a.href);
            if (rel === 'alternate' && a.hreflang) page.hreflang[a.hreflang.toLowerCase()] = decode(a.href);
            if (rel === 'stylesheet' && a.href) page.css.push(new URL(decode(a.href), pageUrl).href);
            if (rel === 'preload' || rel === 'modulepreload') page.preloads.push({ href: decode(a.href ?? ''), as: a.as ?? (rel === 'modulepreload' ? 'script' : ''), imagesrcset: a.imagesrcset ?? null });
        }
        if (tag === 'picture') picture = { modern: null };
        if (tag === 'source' && picture && /webp|avif/i.test(a.type ?? '') && a.srcset) picture.modern ??= new URL(decode(a.srcset.split(',')[0].trim().split(' ')[0]), pageUrl).href;
        // an <img> without an address is a frame a script fills later (the lightbox): not a picture of the page
        if (tag === 'img' && a.src && !skipStack.includes('template')) {
            page.images.push({ src: a.src ? new URL(decode(a.src), pageUrl).href : null, alt: 'alt' in a ? decode(a.alt) : null, width: a.width ?? null, height: a.height ?? null,
                loading: a.loading ?? null, fetchpriority: a.fetchpriority ?? null, srcset: a.srcset ? true : false, inMain: inMain > 0, modern: picture?.modern ?? null });
        }
        if (tag === 'a' && a.href !== undefined) anchor = { href: decode(a.href), rel: (a.rel ?? '').toLowerCase(), text: '', inMain: inMain > 0, where: skipStack.find((s) => ['nav', 'header', 'footer'].includes(s)) ?? 'body' };
        if (tag === 'nav' && /breadcrumb|drobe/i.test((a['aria-label'] ?? '') + ' ' + (a.class ?? '') + ' ' + (a.id ?? ''))) page.breadcrumbNav = true;
        if (/^h[1-6]$/.test(tag)) heading = { tag, text: '' };
        if (tag === 'main') inMain++;
        if (BLOCK.has(tag)) { flush(); all.push(' '); }
        if (SKIP.has(tag) && !VOID.has(tag) && !m[3].trim().endsWith('/')) { skipStack.push(tag); skip++; }
    }
    page.bodyText = clean(all.join(''));
    return page;
}

/** Kinds named anywhere in a JSON-LD document, and the nodes themselves. */
function ldNodes(doc, out = []) {
    if (Array.isArray(doc)) doc.forEach((d) => ldNodes(d, out));
    else if (doc && typeof doc === 'object') {
        if (doc['@type']) out.push(doc);
        for (const v of Object.values(doc)) if (v && typeof v === 'object') ldNodes(v, out);
    }
    return out;
}
const typesOf = (n) => [].concat(n['@type'] ?? []);

function checkStructured(page) {
    const visible = norm(page.bodyText);
    const out = { blocks: page.jsonld.length, invalid: 0, types: [], problems: [] };
    const top = [];
    for (const raw of page.jsonld) {
        let doc;
        try { doc = JSON.parse(raw); } catch { out.invalid++; out.problems.push('JSON-LD does not parse'); continue; }
        const nodes = ldNodes(doc);
        for (const t of [].concat(doc['@graph'] ?? doc).flatMap(typesOf)) top.push(t);
        for (const n of nodes) {
            const kinds = typesOf(n);
            if (kinds.includes('FAQPage')) {
                for (const q of [].concat(n.mainEntity ?? [])) {
                    if (!visible.includes(norm(clean(String(q.name ?? ''))))) out.problems.push(`FAQPage question not on the page: ${String(q.name).slice(0, 60)}`);
                    const answer = norm(clean(String(q.acceptedAnswer?.text ?? '').replace(/<[^>]+>/g, ' ')));
                    if (answer && !visible.includes(answer.slice(0, 60))) out.problems.push(`FAQPage answer not on the page: ${String(q.name).slice(0, 60)}`);
                }
            }
            if (kinds.includes('Product') || kinds.includes('Service')) {
                for (const o of [].concat(n.offers ?? [])) {
                    for (const price of [o.price, o.lowPrice, o.highPrice].filter((p) => p !== undefined && p !== null)) {
                        const digits = String(price).replace(/\.0+$/, '').replace('.', ',');
                        const seen = visible.replace(/(\d)[ \u00a0\u202f](?=\d{3})/g, '$1');
                        if (!seen.includes(digits) && !seen.includes(String(price).replace(/\.0+$/, ''))) out.problems.push(`${kinds[0]} price ${price} not in the visible text`);
                    }
                }
                if (n.aggregateRating || n.review) out.problems.push(`${kinds[0]} carries a rating or a review: check it is real and visible`);
            }
            if (kinds.includes('HowTo')) {
                for (const s of [].concat(n.step ?? [])) if (s.name && !visible.includes(norm(clean(String(s.name))))) out.problems.push(`HowTo step not on the page: ${String(s.name).slice(0, 60)}`);
            }
            if (kinds.some((k) => ['Article', 'BlogPosting', 'NewsArticle'].includes(k))) {
                if (n.headline && page.h1[0] && norm(clean(String(n.headline))) !== norm(page.h1[0])) out.problems.push('Article headline differs from the H1');
                for (const need of ['datePublished', 'author', 'image']) if (!n[need]) out.problems.push(`Article without ${need}`);
            }
            if (kinds.includes('BreadcrumbList')) {
                const items = [].concat(n.itemListElement ?? []);
                items.forEach((it, i) => { if (Number(it.position) !== i + 1) out.problems.push('BreadcrumbList positions are not 1..n'); });
            }
        }
    }
    out.types = [...new Set(top)].sort();
    const count = (k) => top.filter((t) => t === k).length;
    for (const once of ['Organization', 'LocalBusiness', 'FAQPage', 'BreadcrumbList', 'WebSite']) if (count(once) > 1) out.problems.push(`${once} stands on the page ${count(once)} times`);
    out.problems = [...new Set(out.problems)];
    return out;
}

// ------------------------------------------------------------------------------------------------------------ browser

async function openChrome() {
    const port = 9300 + Math.floor(Math.random() * 500);
    const profile = mkdtempSync(join(tmpdir(), 'mp-seo-audit-'));
    const proc = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', 'about:blank'], { stdio: 'ignore' });
    let version = null;
    for (let i = 0; i < 60 && !version; i++) {
        await sleep(250);
        try { version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json(); } catch { /* not up yet */ }
    }
    if (!version) throw new Error('Chrome did not start');
    const ws = new WebSocket(version.webSocketDebuggerUrl);
    await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
    let id = 0; const waiting = new Map(); let listeners = [];
    ws.onmessage = (msg) => {
        const d = JSON.parse(msg.data);
        if (d.id && waiting.has(d.id)) { const { res, rej } = waiting.get(d.id); waiting.delete(d.id); d.error ? rej(new Error(d.error.message)) : res(d.result); } else listeners.forEach((l) => l(d));
    };
    const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const n = ++id; waiting.set(n, { res, rej }); ws.send(JSON.stringify({ id: n, method, params, sessionId })); });
    const close = async () => { try { await send('Browser.close'); } catch { /* gone */ } ws.close(); proc.kill(); await sleep(400); try { rmSync(profile, { recursive: true, force: true }); } catch { /* still locked */ } };
    return { send, close, listen: (l) => { listeners.push(l); return () => { listeners = listeners.filter((x) => x !== l); }; } };
}

const OBSERVE = `(() => { window.__seo = { lcp: 0, lcpEl: '', cls: 0 };
  try { new PerformanceObserver((l) => { for (const e of l.getEntries()) { window.__seo.lcp = e.startTime; const el = e.element; window.__seo.lcpEl = el ? (el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.currentSrc ? ' ' + el.currentSrc.split('/').pop().split('?')[0] : '')) + ' ' + Math.round(e.size) : ''; } }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) {}
  try { new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__seo.cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); } catch (e) {} })();`;

/** A fresh tab per page (cold cache within the tab's context is not guaranteed, so the cache is switched off). */
async function measure(chrome, url) {
    const { targetId } = await chrome.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await chrome.send('Target.attachToTarget', { targetId, flatten: true });
    const cdp = (method, params) => chrome.send(method, params, sessionId);
    let requests = 0; let bytes = 0; let loaded = false; const failed = [];
    const stop = chrome.listen((d) => {
        if (d.sessionId !== sessionId) return;
        if (d.method === 'Network.requestWillBeSent' && !d.params.request.url.startsWith('data:')) requests++;
        if (d.method === 'Network.loadingFinished') bytes += d.params.encodedDataLength ?? 0;
        if (d.method === 'Network.responseReceived' && d.params.response.status >= 400) failed.push(`${d.params.response.status} ${d.params.response.url.replace(BASE, '')}`);
        if (d.method === 'Page.loadEventFired') loaded = true;
    });
    try {
        await cdp('Page.enable'); await cdp('Network.enable'); await cdp('Runtime.enable');
        await cdp('Network.setCacheDisabled', { cacheDisabled: true });
        await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
        await cdp('Emulation.setUserAgentOverride', { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1 HeadlessChrome matplace-seo-audit', acceptLanguage: 'cs' });
        await cdp('Network.setCookie', { name: 'mp_test', value: '1', url: BASE });
        await cdp('Network.setCookie', { name: 'consent', value: 'a0m0', url: BASE });
        await cdp('Page.addScriptToEvaluateOnNewDocument', { source: OBSERVE });
        await cdp('Page.navigate', { url });
        for (let i = 0; i < 120 && !loaded; i++) await sleep(250);
        await sleep(2500);
        const { result } = await cdp('Runtime.evaluate', { expression: 'JSON.stringify(Object.assign({}, window.__seo, { hScroll: document.documentElement.scrollWidth > innerWidth + 1, dom: document.getElementsByTagName("*").length, fcp: (performance.getEntriesByName("first-contentful-paint")[0] || {}).startTime || 0, ttfb: (performance.getEntriesByType("navigation")[0] || {}).responseStart || 0 }))', returnByValue: true });
        const v = JSON.parse(result.value ?? '{}');
        return { lcp: Math.round(v.lcp ?? 0), lcpEl: v.lcpEl ?? '', cls: Math.round((v.cls ?? 0) * 1000) / 1000, fcp: Math.round(v.fcp ?? 0), ttfb: Math.round(v.ttfb ?? 0), requests, kB: Math.round(bytes / 1024), hScroll: Boolean(v.hScroll), dom: v.dom ?? 0, failed, loaded };
    } catch (e) {
        return { error: String(e) };
    } finally {
        stop();
        try { await chrome.send('Target.closeTarget', { targetId }); } catch { /* gone */ }
    }
}

// --------------------------------------------------------------------------------------------------------------- run

const csv = (v) => { const s = v === null || v === undefined ? '' : String(v); return /[",;\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
const median = (xs) => { const s = xs.filter((x) => Number.isFinite(x)).sort((a, b) => a - b); return s.length ? s[Math.floor((s.length - 1) / 2)] : null; };
const pct = (xs, p) => { const s = xs.filter((x) => Number.isFinite(x)).sort((a, b) => a - b); return s.length ? s[Math.min(s.length - 1, Math.floor(s.length * p))] : null; };

async function sitemapUrls() {
    const index = await get(`${BASE}/sitemap.xml`);
    const locs = (xml) => [...xml.matchAll(/<loc>\s*([^<]+?)\s*<\/loc>/g)].map((x) => decode(x[1]));
    const maps = /<sitemapindex/.test(index.html) ? locs(index.html) : [];
    const urls = new Map();
    if (!maps.length) locs(index.html).forEach((u) => urls.set(pathOf(u), 'sitemap.xml'));
    for (const map of maps) {
        const res = await get(onBase(map));
        for (const u of locs(res.html)) urls.set(pathOf(u), pathOf(map).slice(1));
    }
    return urls;
}

async function main() {
    mkdirSync(OUT, { recursive: true });
    const started = new Date();
    const inMap = await sitemapUrls();
    const targets = new Map([...inMap].map(([p, map]) => [p, { path: p, map }]));
    for (const p of IMPORTANT) for (const l of LANGS) { const path = localized(p, l); if (!targets.has(path)) targets.set(path, { path, map: null }); }
    let list = [...targets.values()].filter((t) => !ONLY || ONLY.test(t.path));
    console.log(`${BASE}: ${inMap.size} addresses in the maps, ${list.length} to read`);

    // 1. every page
    const pages = [];
    for (const [i, t] of list.entries()) {
        const res = await get(BASE + t.path);
        const p = { path: t.path, map: t.map, lang: langOf(t.path), status: res.status, chain: res.chain.map((c) => `${c.status} ${pathOf(c.to)}`), finalPath: res.external ? res.url : pathOf(res.url),
            xRobots: res.headers.get('x-robots-tag'), htmlBytes: Buffer.byteLength(res.html), encoding: res.headers.get('content-encoding'), cache: res.headers.get('cache-control') };
        if (res.html) Object.assign(p, parse(res.html, res.url));
        pages.push(p);
        if ((i + 1) % 25 === 0) console.log(`  ${i + 1}/${list.length}`);
    }
    const ok = pages.filter((p) => p.status === 200 && p.title !== undefined);
    const byPath = new Map(pages.map((p) => [p.path, p]));

    // 2. per page: words, structured data, hreflang, links
    const blockPages = new Map(); // lang|block → pages carrying it
    for (const p of ok) {
        p.words = p.blocks.reduce((n, b) => n + words(b), 0);
        p.structured = checkStructured(p);
        for (const b of new Set(p.blocks.map(norm))) { if (words(b) < 4) continue; const k = `${p.lang}|${b}`; blockPages.set(k, (blockPages.get(k) ?? 0) + 1); }
        p.internal = []; p.externalLinks = 0;
        for (const a of p.links) {
            if (/^(mailto:|tel:|javascript:|#)/i.test(a.href) || a.href === '') continue;
            let u; try { u = new URL(a.href, BASE + p.path); } catch { continue; }
            if (u.origin !== new URL(BASE).origin && u.hostname !== 'matplace.com' && u.hostname !== 'www.matplace.com') { p.externalLinks++; continue; }
            p.internal.push({ path: (u.pathname.replace(/\/$/, '') || '/') + u.search, rel: a.rel, text: a.text, where: a.where, inMain: a.inMain });
        }
    }
    for (const p of ok) {
        let shared = 0; let total = 0;
        for (const b of p.blocks) { const w = words(b); if (w < 4) continue; total += w; if ((blockPages.get(`${p.lang}|${norm(b)}`) ?? 0) > 5) shared += w; }
        p.sharedWords = shared; p.ownWords = total - shared; p.sharedShare = total ? Math.round((shared / total) * 100) : 0;
    }

    // 3. uniqueness within the language
    const dup = (field) => {
        const seen = new Map();
        for (const p of ok) { const v = p[field]; if (!v) continue; const k = `${p.lang}|${v}`; seen.set(k, [...(seen.get(k) ?? []), p.path]); }
        for (const p of ok) { const others = (seen.get(`${p.lang}|${p[field]}`) ?? []).filter((x) => x !== p.path); p[`${field}Dup`] = others; }
    };
    dup('title'); dup('description');
    const h1Seen = new Map();
    for (const p of ok) if (p.h1[0]) h1Seen.set(`${p.lang}|${p.h1[0]}`, [...(h1Seen.get(`${p.lang}|${p.h1[0]}`) ?? []), p.path]);
    for (const p of ok) p.h1Dup = (h1Seen.get(`${p.lang}|${p.h1[0]}`) ?? []).filter((x) => x !== p.path);

    // 4. hreflang reciprocity
    for (const p of ok) {
        p.hreflangProblems = [];
        const langs = Object.keys(p.hreflang);
        if (!langs.length) {
            // a page that exists in one language only (an article without a translation) needs none
            const twins = LANGS.filter((l) => l !== p.lang && byPath.get(localized(bare(p.path), l))?.status === 200);
            if (!/noindex/.test(p.robots ?? '') && twins.length) p.hreflangProblems.push(`no hreflang, yet the page exists in ${twins.join(', ')}`);
            continue;
        }
        if (!p.hreflang['x-default']) p.hreflangProblems.push('no x-default');
        if (pathOf(p.hreflang[p.lang] ?? '') !== p.path) p.hreflangProblems.push('does not name itself');
        for (const l of langs.filter((x) => x !== 'x-default' && x !== p.lang)) {
            const twin = byPath.get(pathOf(p.hreflang[l]));
            if (!twin) { p.hreflangProblems.push(`${l} twin not among the audited pages: ${pathOf(p.hreflang[l])}`); continue; }
            if (twin.status !== 200) p.hreflangProblems.push(`${l} twin answers ${twin.status}`);
            else if (pathOf(twin.hreflang?.[p.lang] ?? '') !== p.path) p.hreflangProblems.push(`${l} twin does not name this page back`);
        }
    }

    // 5. the link graph: who links to whom, clicks from the home page, links that redirect or break
    const inbound = new Map();
    for (const p of ok) for (const path of new Set(p.internal.filter((a) => !/nofollow/.test(a.rel)).map((a) => a.path))) if (path !== p.path) inbound.set(path, [...(inbound.get(path) ?? []), p.path]);
    for (const p of ok) { p.inbound = (inbound.get(p.path) ?? []).length; p.inboundFromMain = ok.filter((q) => q.path !== p.path && q.internal.some((a) => a.path === p.path && a.inMain)).length; }
    for (const lang of LANGS) {
        const home = localized('/', lang); const depth = new Map([[home, 0]]); const queue = [home];
        while (queue.length) {
            const at = queue.shift(); const page = byPath.get(at);
            if (!page?.internal) continue;
            for (const a of page.internal) if (!/nofollow/.test(a.rel) && !depth.has(a.path) && byPath.has(a.path)) { depth.set(a.path, depth.get(at) + 1); queue.push(a.path); }
        }
        for (const p of ok) if (p.lang === lang) p.depth = depth.has(p.path) ? depth.get(p.path) : null;
    }
    const linkStatus = new Map();
    if (LINKS) {
        const all = new Set(); for (const p of ok) for (const a of p.internal) all.add(a.path);
        const unknown = [...all].filter((x) => !byPath.has(x) && !/^\/(storage|build|img|fonts|og)\//.test(x) && !/\.(png|jpe?g|webp|svg|stl|3mf|zip|pdf|xml|mp4)(\?|$)/i.test(x));
        console.log(`  ${all.size} different internal links, ${unknown.length} outside the audited pages to open`);
        for (const path of unknown) { const res = await polite(BASE + path); linkStatus.set(path, { status: res.status, to: res.headers.get('location') ? pathOf(new URL(res.headers.get('location'), BASE + path).href) : null }); }
        for (const p of pages) linkStatus.set(p.path, { status: p.chain.length ? Number(p.chain[0].split(' ')[0]) : p.status, to: p.chain.length ? p.chain[0].split(' ')[1] : null });
        for (const p of ok) {
            p.linkProblems = [];
            for (const path of new Set(p.internal.map((a) => a.path))) {
                const s = linkStatus.get(path); if (!s || s.status === 200) continue;
                // a private page sends a guest to the login: fine when robots may not follow it anyway
                const rel = p.internal.find((a) => a.path === path)?.rel ?? '';
                p.linkProblems.push({ path, status: s.status, to: s.to, nofollow: /nofollow/.test(rel) });
            }
        }
    }

    // 6. pictures and assets by weight
    const weigh = new Map();
    const weight = async (url) => {
        if (weigh.has(url)) return weigh.get(url);
        const res = await polite(url);
        const buf = res.status === 200 ? await res.arrayBuffer() : new ArrayBuffer(0);
        const v = { status: res.status, bytes: buf.byteLength, type: res.headers.get('content-type') ?? '', cache: res.headers.get('cache-control') ?? '' };
        weigh.set(url, v); return v;
    };
    for (const p of ok) {
        p.cssBytes = 0; p.jsBytes = 0;
        for (const u of p.css) p.cssBytes += (await weight(u)).bytes;
        for (const u of p.js) p.jsBytes += (await weight(u)).bytes;
        p.imageProblems = []; p.imageBytes = 0;
        const main = p.images.filter((i) => i.inMain);
        p.imgCount = p.images.length; p.imgNoAlt = p.images.filter((i) => i.alt === null).length; p.imgEmptyAlt = main.filter((i) => i.alt === '').length;
        p.imgNoSize = p.images.filter((i) => !i.width || !i.height).length; p.imgLazy = p.images.filter((i) => i.loading === 'lazy').length;
        p.imgOld = 0; p.imgHeavy = 0;
        for (const img of p.images) {
            if (!img.src || img.src.startsWith('data:')) continue;
            // what a browser takes: the WebP or AVIF source of a <picture> when there is one, else the <img> itself
            const taken = img.modern ?? img.src;
            const ext = (new URL(taken).pathname.match(/\.([a-z0-9]+)$/i)?.[1] ?? '').toLowerCase();
            img.format = ext;
            if (IMAGES && taken.startsWith(BASE)) { const w = await weight(taken); img.bytes = w.bytes; img.status = w.status; p.imageBytes += w.bytes; if (w.status !== 200) p.imageProblems.push(`picture answers ${w.status}: ${pathOf(img.src)}`); if (w.bytes > 150 * 1024) p.imgHeavy++; }
            if (['png', 'jpg', 'jpeg', 'gif'].includes(ext) && (img.bytes ?? 0) > 30 * 1024) p.imgOld++;
        }
        if (IMAGES && p.og['og:image']) { const w = await weight(onBase(p.og['og:image'])); p.ogImageStatus = w.status; p.ogImageBytes = w.bytes; }
    }

    // 7. the phone
    if (BROWSER) {
        let chosen = ok;
        if (BROWSER === 'important') { const want = new Set([...IMPORTANT, ...CAMPAIGN].flatMap((x) => LANGS.map((l) => localized(x, l)))); chosen = ok.filter((p) => want.has(p.path) && (p.lang === 'cs' || CAMPAIGN.includes(bare(p.path)))); }
        else if (/^\d+$/.test(String(BROWSER))) chosen = ok.slice(0, Number(BROWSER));
        console.log(`  Chrome 390 px: ${chosen.length} pages`);
        const chrome = await openChrome();
        try { for (const p of chosen) { p.phone = await measure(chrome, BASE + p.path); await sleep(Math.max(600, ((p.phone.requests ?? 0) * 1000) / RATE)); } } finally { await chrome.close(); }
    }

    // 8. findings per page
    const campaign = new Set(CAMPAIGN.flatMap((x) => LANGS.map((l) => localized(x, l))));
    const rank = (p) => (CAMPAIGN.includes(bare(p.path)) ? (p.lang === 'cs' ? 0 : 1) : IMPORTANT.includes(bare(p.path)) ? 2 : p.lang === 'cs' ? 3 : 4);
    for (const p of pages) {
        const f = [];
        if (p.status !== 200) { f.push(`answers ${p.status}${p.chain.length ? ' after ' + p.chain.join(' → ') : ''}`); p.findings = f; continue; }
        if (p.chain.length) f.push(`redirects: ${p.chain.join(' → ')}`);
        if (/noindex/.test(p.robots ?? '') || /noindex/.test(p.xRobots ?? '')) f.push(`noindex (${p.robots ?? p.xRobots})`);
        else if (!p.canonical) f.push('no canonical');
        else if (pathOf(p.canonical) !== p.finalPath) f.push(`canonical points elsewhere: ${pathOf(p.canonical)}`);
        if (p.lang !== p.lang?.toLowerCase() || (p.lang && p.lang !== langOf(p.path) && false)) f.push('html lang differs');
        f.push(...(p.hreflangProblems ?? []).map((x) => `hreflang: ${x}`));
        const tl = [...(p.title ?? '')].length; const dl = [...(p.description ?? '')].length;
        if (!p.title) f.push('no title'); else { if (tl > 60) f.push(`title ${tl} characters (over 60)`); if (tl < 25) f.push(`title ${tl} characters (short)`); }
        if (p.titles > 1) f.push(`${p.titles} <title> elements`);
        if (p.titleDup?.length) f.push(`title shared with ${p.titleDup.slice(0, 3).join(', ')}`);
        if (!p.description) f.push('no description'); else { if (dl > 160) f.push(`description ${dl} characters (over 160)`); if (dl < 70) f.push(`description ${dl} characters (short)`); }
        if (p.descriptionDup?.length) f.push(`description shared with ${p.descriptionDup.slice(0, 3).join(', ')}`);
        if (p.h1.length !== 1) f.push(`${p.h1.length} H1`);
        if (p.h1Dup?.length) f.push(`H1 shared with ${p.h1Dup.slice(0, 3).join(', ')}`);
        const levels = p.outline.map((h) => h.level);
        if (levels.some((l, i) => i > 0 && l > levels[i - 1] + 1)) f.push('heading outline skips a level');
        if (p.words < 250) f.push(`${p.words} words of own text`);
        if (p.sharedShare > 40) f.push(`${p.sharedShare} % of the text stands on other pages too`);
        if (p.imgNoAlt) f.push(`${p.imgNoAlt} pictures without alt`);
        if (p.imgNoSize) f.push(`${p.imgNoSize} pictures without width/height`);
        if (p.imgOld) f.push(`${p.imgOld} PNG/JPG pictures over 30 kB`);
        if (p.imgHeavy) f.push(`${p.imgHeavy} pictures over 150 kB`);
        f.push(...(p.imageProblems ?? []));
        if (p.inbound === 0) f.push('no audited page links here (orphan)');
        else if (p.inboundFromMain === 0) f.push('linked only from menus and footers');
        if (p.depth === null) f.push('not reachable from the home page'); else if (p.depth > 2) f.push(`${p.depth} clicks from the home page`);
        for (const l of p.linkProblems ?? []) if (!l.nofollow || l.status >= 400) f.push(`link to ${l.path} answers ${l.status}${l.to ? ' → ' + l.to : ''}`);
        if (!p.structured.blocks) f.push('no structured data');
        f.push(...p.structured.problems);
        if (!p.structured.types.includes('BreadcrumbList') && p.path !== localized('/', p.lang)) f.push('no BreadcrumbList');
        for (const need of ['og:title', 'og:description', 'og:image', 'og:url', 'og:type']) if (!p.og[need]) f.push(`no ${need}`);
        if (p.ogImageStatus !== undefined && p.ogImageStatus !== 200) f.push(`og:image answers ${p.ogImageStatus}`);
        if (p.htmlBytes > 200 * 1024) f.push(`HTML ${Math.round(p.htmlBytes / 1024)} kB`);
        if (p.phone && !p.phone.error) {
            if (p.phone.lcp > 2500) f.push(`LCP ${p.phone.lcp} ms on the phone (${p.phone.lcpEl})`);
            if (p.phone.cls > 0.1) f.push(`CLS ${p.phone.cls}`);
            if (p.phone.hScroll) f.push('wider than the phone');
            if (p.phone.failed.length) f.push(`failed requests: ${p.phone.failed.slice(0, 3).join(', ')}`);
        }
        p.findings = f;
    }

    // 9. files
    const count = (fn) => ok.filter(fn).length;
    const len = (s) => [...(s ?? '')].length;
    const summary = {
        base: BASE, at: started.toISOString(), inMaps: inMap.size, read: pages.length, ok: ok.length,
        notOk: pages.filter((p) => p.status !== 200).map((p) => `${p.path} ${p.status}`),
        redirected: count((p) => p.chain.length > 0), noindex: count((p) => /noindex/.test(p.robots ?? '')),
        canonicalElsewhere: count((p) => p.canonical && pathOf(p.canonical) !== p.finalPath), hreflangProblems: count((p) => p.hreflangProblems?.length),
        titleOver60: count((p) => len(p.title) > 60), titleOver70: count((p) => len(p.title) > 70), titleDup: count((p) => p.titleDup?.length), titleMedian: median(ok.map((p) => len(p.title))),
        descOver160: count((p) => len(p.description) > 160), descUnder70: count((p) => len(p.description) < 70), descDup: count((p) => p.descriptionDup?.length), descMedian: median(ok.map((p) => len(p.description))),
        h1NotOne: count((p) => p.h1.length !== 1), h1Dup: count((p) => p.h1Dup?.length), outlineSkips: count((p) => p.findings.includes('heading outline skips a level')),
        wordsMedian: median(ok.map((p) => p.words)), wordsUnder250: count((p) => p.words < 250), sharedShareMedian: median(ok.map((p) => p.sharedShare)), sharedOver40: count((p) => p.sharedShare > 40),
        ownWordsMedian: median(ok.map((p) => p.ownWords)),
        imgNoAlt: ok.reduce((n, p) => n + p.imgNoAlt, 0), imgNoSize: ok.reduce((n, p) => n + p.imgNoSize, 0), pagesWithOldImages: count((p) => p.imgOld), pagesWithHeavyImages: count((p) => p.imgHeavy),
        imageKBMedian: median(ok.map((p) => Math.round(p.imageBytes / 1024))), imageKBMax: Math.max(0, ...ok.map((p) => Math.round(p.imageBytes / 1024))),
        orphans: count((p) => p.inbound === 0), onlyMenus: count((p) => p.inbound > 0 && p.inboundFromMain === 0), unreachable: count((p) => p.depth === null), deeperThan2: count((p) => p.depth !== null && p.depth > 2),
        linksToRedirects: LINKS ? [...new Set(ok.flatMap((p) => p.linkProblems.filter((l) => l.status >= 300 && l.status < 400 && !l.nofollow).map((l) => l.path)))].length : null,
        linksBroken: LINKS ? [...new Set(ok.flatMap((p) => p.linkProblems.filter((l) => l.status >= 400 || l.status === 0).map((l) => `${l.path} ${l.status}`)))] : null,
        noStructured: count((p) => !p.structured.blocks), invalidJsonLd: count((p) => p.structured.invalid), structuredProblems: count((p) => p.structured.problems.length),
        noBreadcrumb: count((p) => !p.structured.types.includes('BreadcrumbList') && p.path !== localized('/', p.lang)),
        types: Object.fromEntries([...new Set(ok.flatMap((p) => p.structured.types))].sort().map((t) => [t, count((p) => p.structured.types.includes(t))])),
        ogIncomplete: count((p) => ['og:title', 'og:description', 'og:image', 'og:url', 'og:type'].some((k) => !p.og[k])),
        htmlKBMedian: median(ok.map((p) => Math.round(p.htmlBytes / 1024))), htmlKBMax: Math.max(0, ...ok.map((p) => Math.round(p.htmlBytes / 1024))), htmlOver200: count((p) => p.htmlBytes > 200 * 1024),
        cssKB: median(ok.map((p) => Math.round(p.cssBytes / 1024))), jsKB: median(ok.map((p) => Math.round(p.jsBytes / 1024))),
        phone: (() => { const ph = ok.filter((p) => p.phone && !p.phone.error).map((p) => p.phone); return ph.length ? { pages: ph.length, lcpMedian: median(ph.map((x) => x.lcp)), lcpP90: pct(ph.map((x) => x.lcp), 0.9), lcpOver2500: ph.filter((x) => x.lcp > 2500).length,
            clsMax: Math.max(...ph.map((x) => x.cls)), clsOver01: ph.filter((x) => x.cls > 0.1).length, requestsMedian: median(ph.map((x) => x.requests)), kBMedian: median(ph.map((x) => x.kB)), hScroll: ph.filter((x) => x.hScroll).length } : null; })(),
        pagesWithFindings: pages.filter((p) => p.findings.length).length, findings: pages.reduce((n, p) => n + p.findings.length, 0),
    };
    const slim = pages.map((p) => { const { blocks, bodyText, links, jsonld, ...rest } = p; return rest; });
    writeFileSync(join(OUT, 'pages.json'), JSON.stringify(slim, null, 1));
    writeFileSync(join(OUT, 'summary.json'), JSON.stringify(summary, null, 2));
    const cols = ['path', 'lang', 'map', 'status', 'chain', 'robots', 'canonical', 'hreflang', 'titleLen', 'title', 'descLen', 'description', 'h1Count', 'h1', 'headings', 'words', 'sharedShare', 'ownWords',
        'img', 'imgNoAlt', 'imgNoSize', 'imgOld', 'imgKB', 'linksOut', 'inbound', 'inboundFromMain', 'depth', 'types', 'structuredProblems', 'og', 'htmlKB', 'cssKB', 'jsKB', 'lcp', 'cls', 'requests', 'kB', 'findings'];
    const row = (p) => ({ path: p.path, lang: p.lang, map: p.map ?? '', status: p.status, chain: p.chain.join(' → '), robots: p.robots ?? '', canonical: p.canonical ? pathOf(p.canonical) : '',
        hreflang: Object.keys(p.hreflang ?? {}).join(' '), titleLen: len(p.title), title: p.title ?? '', descLen: len(p.description), description: p.description ?? '', h1Count: p.h1?.length ?? '', h1: (p.h1 ?? []).join(' | '),
        headings: (p.outline ?? []).map((h) => h.level).join(''), words: p.words ?? '', sharedShare: p.sharedShare ?? '', ownWords: p.ownWords ?? '', img: p.imgCount ?? '', imgNoAlt: p.imgNoAlt ?? '', imgNoSize: p.imgNoSize ?? '',
        imgOld: p.imgOld ?? '', imgKB: p.imageBytes !== undefined ? Math.round(p.imageBytes / 1024) : '', linksOut: p.internal?.length ?? '', inbound: p.inbound ?? '', inboundFromMain: p.inboundFromMain ?? '', depth: p.depth ?? '',
        types: (p.structured?.types ?? []).join(' '), structuredProblems: (p.structured?.problems ?? []).length, og: Object.keys(p.og ?? {}).length, htmlKB: Math.round(p.htmlBytes / 1024), cssKB: p.cssBytes !== undefined ? Math.round(p.cssBytes / 1024) : '',
        jsKB: p.jsBytes !== undefined ? Math.round(p.jsBytes / 1024) : '', lcp: p.phone?.lcp ?? '', cls: p.phone?.cls ?? '', requests: p.phone?.requests ?? '', kB: p.phone?.kB ?? '', findings: p.findings.join(' | ') });
    writeFileSync(join(OUT, 'pages.csv'), '\ufeff' + [cols.join(';'), ...pages.map((p) => { const r = row(p); return cols.map((c) => csv(r[c])).join(';'); })].join('\n') + '\n');

    const md = [`# SEO audit of ${BASE}`, '', `${started.toISOString().slice(0, 16).replace('T', ' ')} UTC · ${summary.inMaps} addresses in the maps · ${summary.read} read · ${summary.ok} answer 200`, '', '| measure | value |', '|---|---|'];
    for (const [k, v] of Object.entries(summary)) if (!['base', 'at'].includes(k)) md.push(`| ${k} | ${typeof v === 'object' && v !== null ? JSON.stringify(v) : v} |`);
    writeFileSync(join(OUT, 'summary.md'), md.join('\n') + '\n');

    const sorted = [...pages].filter((p) => p.findings.length).sort((a, b) => rank(a) - rank(b) || b.findings.length - a.findings.length || a.path.localeCompare(b.path));
    const fm = [`# Findings on ${BASE}`, '', 'The pages of the campaign and the entry pages first, then Czech, then the other languages.', ''];
    const tally = new Map();
    for (const p of pages) for (const f of p.findings) { const k = f.replace(/\d+(?:[.,]\d+)?/g, 'N').replace(/(with|to|from|here:|elsewhere:|page:|after|redirects:) .*/, '$1 …').slice(0, 70); tally.set(k, (tally.get(k) ?? 0) + 1); }
    fm.push('## How often', '', '| finding | pages |', '|---|---|', ...[...tally].sort((a, b) => b[1] - a[1]).map(([k, n]) => `| ${k} | ${n} |`), '', '## Page by page', '');
    for (const p of sorted) fm.push(`- \`${p.path}\`${campaign.has(p.path) ? ' **(campaign)**' : ''}: ${p.findings.join('; ')}`);
    writeFileSync(join(OUT, 'findings.md'), fm.join('\n') + '\n');

    console.log(md.slice(4).join('\n'));
    console.log(`\nWritten to ${OUT}: pages.json, pages.csv, summary.json, summary.md, findings.md`);
}

/** Two runs side by side: the table of the report (before a change and after it, or the local server and production). */
function compare(spec) {
    const [a, b] = String(spec).split(',').map((f) => JSON.parse(readFileSync(f.endsWith('.json') ? f : join(f, 'summary.json'), 'utf8')));
    const show = (v) => (v === null || v === undefined ? '–' : Array.isArray(v) ? (v.length ? `${v.length}: ${v.slice(0, 4).join(', ')}${v.length > 4 ? ', …' : ''}` : '0') : typeof v === 'object' ? JSON.stringify(v) : String(v));
    const rows = [`| measure | ${a.base} | ${b.base} |`, '|---|---|---|'];
    for (const k of Object.keys(a)) {
        if (['base', 'at', 'types', 'phone'].includes(k)) continue;
        rows.push(`| ${k} | ${show(a[k])} | ${show(b[k])} |`);
    }
    for (const k of new Set([...Object.keys(a.types ?? {}), ...Object.keys(b.types ?? {})])) rows.push(`| pages with ${k} | ${a.types?.[k] ?? 0} | ${b.types?.[k] ?? 0} |`);
    for (const k of new Set([...Object.keys(a.phone ?? {}), ...Object.keys(b.phone ?? {})])) rows.push(`| phone ${k} | ${show(a.phone?.[k])} | ${show(b.phone?.[k])} |`);
    console.log(rows.join('\n'));
}

if (arg('compare', null)) compare(arg('compare'));
else main().catch((e) => { console.error(e); process.exit(1); });
