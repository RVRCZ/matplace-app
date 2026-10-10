/**
 * The 3D map of a city or a landscape, as a module of the tool page: a place (searched, or coordinates), the kind
 * and the range of the map, the area drawn from above by the server, the settings, a colour per part, and the model
 * made in the queue (the page asks for the file until it is ready and shows the phase). The viewer, the status line,
 * the price card and the downloads are the page's (tool_page.ts).
 */
import type { Stage } from './tool_page';
import type { Region } from './viewer';
import { colorOf, paintSwatch, pickColor, rememberColor } from './colors';
import { FileInfo } from './api';
import { loadGeometryFromUrl } from './loaders';

interface Cfg { places: string; preview: string; create: string; home: string; files: string; from: string | null; sides: Record<string, number[]>; parts: Record<string, string[]>; colors: Record<string, string>; i18n: Record<string, string> }
interface Place { name: string; kind: string; country: string; display: string; lat: number; lon: number }
interface MapNotes { stage?: string; scale?: string; buildings?: number; roads_m?: number; osm_date?: string; relief_m?: number; warnings?: string[]; regions?: Region[]; parts?: string[]; type?: string }
type MapFile = FileInfo & { map?: MapNotes | null; tool?: { kind: string; params: Record<string, unknown>; url: string } | null };

export function bootMap(stage: Stage): void {
    const cfg = (window as unknown as { MP_MAP?: Cfg }).MP_MAP;
    const form = document.getElementById('map-form') as HTMLFormElement | null;
    if (!cfg || !form) return;
    const t = (k: string, r: Record<string, string | number> = {}) => Object.entries(r).reduce((s, [a, b]) => s.split(`:${a}`).join(String(b)), cfg.i18n[k] ?? stage.t(k));
    const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;
    const nf = stage.nf;
    const esc = (s: string): string => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
    const post = (url: string, body: Record<string, unknown>) => fetch(url, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify(body) });
    const firstError = (b: { message?: string; errors?: Record<string, string[]> }, status = 0) => (status === 429 ? (b.message ?? t('param.too_fast')) : Object.values(b.errors ?? {})[0]?.[0] ?? b.message ?? t(status >= 500 || status === 0 ? 'param.preview_failed' : 'param.failed'));
    const errorOf = async (res: Response): Promise<string> => { try { return firstError(await res.json(), res.status); } catch { return firstError({}, res.status); } };

    let place: Place | null = null;
    let previewSeq = 0; let previewTimer = 0; let polling = 0;
    let made: MapFile | null = null;
    const partColors: Record<string, string> = {};

    const type = (): string => form.querySelector<HTMLInputElement>('[data-choice="type"]:checked')?.value ?? 'city';
    const params = (): Record<string, unknown> => {
        const p: Record<string, unknown> = {};
        form.querySelectorAll<HTMLInputElement>('[data-param]').forEach((i) => { p[i.dataset.param!] = Number(i.value); });
        form.querySelectorAll<HTMLInputElement>('[data-flag]').forEach((i) => { p[i.dataset.flag!] = i.checked; });
        form.querySelectorAll<HTMLInputElement>('[data-choice]:checked').forEach((i) => { p[i.dataset.choice!] = i.value; });
        form.querySelectorAll<HTMLInputElement>('[data-text]').forEach((i) => { p[i.dataset.text!] = i.value; });
        if (place) { p.lat = place.lat; p.lon = place.lon; p.place = place.name; }
        p.part_colors = Object.fromEntries((cfg.parts[type()] ?? []).map((part) => [part, partColors[part] ?? cfg.colors[part]]));
        return p;
    };
    const fieldOf = (id: string): HTMLInputElement | null => form.querySelector<HTMLInputElement>(`[data-param="${id}"]`);
    const syncRange = (num: HTMLInputElement): void => { const r = form.querySelector<HTMLInputElement>(`[data-range="${num.dataset.param}"]`); if (r && r.value !== num.value) r.value = num.value; };
    form.querySelectorAll<HTMLInputElement>('[data-range]').forEach((r) => r.addEventListener('input', (e) => {
        const num = fieldOf(r.dataset.range!); if (!num) return;
        e.stopPropagation(); num.value = r.value; num.dispatchEvent(new Event('input', { bubbles: true }));
    }));

    /** What belongs to one kind of map only (data-when="type=city"), to the frame (frame=on): folded away otherwise. */
    const applyWhen = (): void => {
        const p = params();
        form.querySelectorAll<HTMLElement>('[data-when]').forEach((el) => {
            const [key, list] = el.dataset.when!.split('=');
            const now = typeof p[key] === 'boolean' ? (p[key] ? 'on' : 'off') : String(p[key] ?? '');
            el.classList.toggle('hidden', !list.split(',').includes(now));
        });
        // the ranges a kind of map offers; a range the kind does not offer falls back to its middle one
        const offered = cfg.sides[type()] ?? [];
        let ok = false;
        form.querySelectorAll<HTMLElement>('[data-side-for]').forEach((label) => {
            const input = label.querySelector<HTMLInputElement>('input')!;
            const on = offered.includes(Number(input.value));
            label.classList.toggle('hidden', !on);
            if (on && input.checked) ok = true;
        });
        if (!ok) {
            const pick = form.querySelector<HTMLInputElement>(`[data-choice="side"][value="${offered[1] ?? offered[0]}"]`);
            if (pick) pick.checked = true;
        }
        renderParts();
        renderScale();
    };

    const renderScale = (): void => {
        const el = $('map-scale'); const size = Number(fieldOf('size')?.value ?? 150);
        const frame = (form.querySelector<HTMLInputElement>('[data-flag="frame"]')?.checked ? Number(fieldOf('frame_mm')?.value ?? 6) : 0);
        const side = Number(form.querySelector<HTMLInputElement>('[data-choice="side"]:checked')?.value ?? 1000);
        const inner = size - 2 * frame;
        if (inner <= 0) { el.textContent = ''; return; }
        const n = side * 1000 / inner;
        const step = 10 ** Math.max(0, Math.floor(Math.log10(n)) - 1);
        el.textContent = t('map.scale', { s: `1 : ${nf.format(Math.round(n / step) * step)}` });
    };

    // ── the colours: one row per part of the kind of map, the window of colours on a click ──
    const renderParts = (): void => {
        const box = $('map-parts');
        box.innerHTML = '';
        (cfg.parts[type()] ?? []).forEach((part) => {
            const hex = partColors[part] ?? cfg.colors[part];
            const row = document.createElement('div');
            row.className = 'tool-swatch-row'; row.dataset.part = part;
            row.innerHTML = `<button type="button" class="tool-swatch" aria-label="${esc(t(`map.part.${part}`))}: ${esc(t('toolpage.color.pick'))}"></button>
                <span class="min-w-0 text-sm"><span class="block font-medium text-ink">${esc(t(`map.part.${part}`))}</span><span class="block truncate text-muted">${esc(colorOf(hex)?.name ?? hex)} · ${esc(hex)}</span></span>`;
            const swatch = row.querySelector<HTMLElement>('.tool-swatch')!;
            paintSwatch(swatch, hex);
            swatch.onclick = async () => {
                const picked = await pickColor(hex);
                if (!picked) return;
                partColors[part] = picked; rememberColor(picked);
                renderParts(); previewSoon();
            };
            box.appendChild(row);
        });
    };

    // ── the place: a name searched, the places it may mean, one picked ──
    const say = (text: string | null): void => { const el = $('map-place-state'); el.textContent = text ?? ''; el.classList.toggle('hidden', !text); };
    const choose = (p: Place): void => {
        place = p;
        $('map-places').classList.add('hidden');
        const chosen = $('map-chosen'); chosen.textContent = t('map.place.chosen', { name: p.display || p.name }); chosen.classList.remove('hidden');
        const name = form.querySelector<HTMLInputElement>('[data-text="name"]');
        if (name && !name.dataset.typed) name.value = p.kind === 'point' ? '' : p.name;
        stage.go({ disabled: false });
        previewSoon(0);
    };
    const search = async (): Promise<void> => {
        const q = ($('map-place') as HTMLInputElement).value.trim();
        if (q.length < 2) return;
        say(t('map.place.searching'));
        $('map-places').classList.add('hidden');
        try {
            const res = await post(cfg.places, { q });
            if (!res.ok) { say(await errorOf(res)); return; }
            const places = (await res.json()).places as Place[];
            if (!places.length) { say(t('map.place.none')); return; }
            if (places.length === 1) { say(null); choose(places[0]); return; }
            say(t('map.place.pick'));
            const list = $('map-places'); list.innerHTML = '';
            places.forEach((p) => {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'block w-full rounded-lg border border-line px-3 py-2 text-left text-sm hover:border-ink';
                b.innerHTML = `<span class="font-medium text-ink">${esc(p.name)}</span> <span class="text-muted">· ${esc([p.kind, p.country].filter(Boolean).join(' · '))}</span><span class="block truncate text-xs text-muted">${esc(p.display)}</span>`;
                b.onclick = () => { say(null); choose(p); };
                list.appendChild(b);
            });
            list.classList.remove('hidden');
        } catch { say(t('param.preview_failed')); }
    };
    $('map-search').onclick = () => { void search(); };
    ($('map-place') as HTMLInputElement).addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); void search(); } });
    form.querySelector<HTMLInputElement>('[data-text="name"]')?.addEventListener('input', (e) => { (e.target as HTMLInputElement).dataset.typed = '1'; });

    // ── the area from above: the server draws it from the data the model is built from ──
    const preview = async (): Promise<void> => {
        if (!place) return;
        const mine = ++previewSeq;
        const box = $('map-preview-box'); const busy = $('map-preview-busy'); const img = $('map-preview') as HTMLImageElement;
        box.classList.remove('hidden'); busy.classList.remove('hidden'); busy.classList.add('flex');
        try {
            const res = await post(cfg.preview, { params: params() });
            if (mine !== previewSeq) return;
            if (!res.ok) { stage.error(await errorOf(res)); return; }
            const meta = JSON.parse(res.headers.get('X-Map-Meta') ?? '{}') as { buildings?: number; roads_m?: number; scale?: string; relief_m?: number };
            const url = URL.createObjectURL(await res.blob());
            img.onload = () => URL.revokeObjectURL(url);
            img.src = url;
            $('map-preview-facts').textContent = type() === 'landscape'
                ? t('map.place.preview.facts.landscape', { h: nf.format(meta.relief_m ?? 0), s: meta.scale ?? '' })
                : t('map.place.preview.facts', { b: meta.buildings ?? 0, r: nf.format(meta.roads_m ?? 0), s: meta.scale ?? '' });
            stage.error(null);
        } catch {
            if (mine === previewSeq) stage.error(t('param.preview_failed'));
        } finally {
            if (mine === previewSeq) { busy.classList.add('hidden'); busy.classList.remove('flex'); }
        }
    };
    const previewSoon = (ms = 400): void => { window.clearTimeout(previewTimer); previewTimer = window.setTimeout(() => { void preview(); }, ms); };

    // ── the model, made in the queue ──
    const wait = (k: string | null, p: Record<string, string | number> = {}): void => { const el = $('map-wait'); el.textContent = k ? t(k, p) : ''; el.classList.toggle('hidden', !k); };
    const untilDone = async (info: MapFile): Promise<MapFile> => {
        const mine = ++polling;
        for (let i = 0; i < 400 && info.status !== 'ready' && info.status !== 'failed'; i++) {
            const phase = info.map?.stage ?? 'queued';
            wait(cfg.i18n[`map.stage.${phase}`] ? `map.stage.${phase}` : 'map.creating');
            await new Promise((r) => setTimeout(r, 1500));
            if (mine !== polling) return info;
            info = (await stage.fetchFile(info.uuid)) as MapFile;
        }
        return info;
    };
    const showResult = async (file: MapFile): Promise<void> => {
        made = file;
        wait(null);
        const n = file.map ?? {};
        if (file.stl_url) stage.show(await loadGeometryFromUrl(file.stl_url), { kind: 'map', regions: n.regions ?? null });
        stage.status({ bbox: file.bbox, pieces: 1, colors: (n.parts ?? []).length });
        if (stage.cfg.price && file.volume_mm3) stage.price(stage.cfg.price, { volume_mm3: file.volume_mm3, area_mm2: file.area_mm2 }, {});
        stage.fileDownloads(file, (p) => t(`map.part.${p}`));
        stage.go({ href: `${cfg.home}?open=${file.uuid}`, disabled: false, label: t('toolpage.go') });
        const facts = $('map-facts');
        facts.textContent = n.type === 'landscape'
            ? t('map.facts.landscape', { h: nf.format(n.relief_m ?? 0), e: nf.format(Number((file.tool?.params.exaggeration as number | undefined) ?? 1)), d: n.osm_date ?? '' })
            : t('map.facts.city', { b: n.buildings ?? 0, r: nf.format(n.roads_m ?? 0), d: n.osm_date ?? '' });
        facts.classList.remove('hidden');
        stage.warnings({ place: (n.warnings ?? []).map((w) => cfg.i18n[`map.warn.${w}`] ? t(`map.warn.${w}`) : w) });
    };
    const create = async (): Promise<void> => {
        if (!place) { stage.reveal('place', $('map-place')); return; }
        stage.go({ disabled: true, label: t('map.creating') });
        stage.busy(true); stage.error(null);
        try {
            const res = await post(cfg.create, { params: params() });
            if (!res.ok) { stage.error(await errorOf(res)); stage.go({ disabled: false, label: t('map.go') }); return; }
            wait('map.queued');
            const file = await untilDone((await res.json()).file as MapFile);
            if (file.status !== 'ready') {
                const code = (file.error ?? 'map_failed').replace(/:.*$/, '');
                wait(null);
                stage.error(cfg.i18n[`map.error.${code}`] ? t(`map.error.${code}`) : t('map.failed'));
                stage.go({ disabled: false, label: t('map.go') });
                return;
            }
            await showResult(file);
            stage.reveal('colors');
        } catch {
            stage.error(t('param.preview_failed')); stage.go({ disabled: false, label: t('map.go') });
        } finally {
            stage.busy(false);
        }
    };
    stage.go({ run: create, disabled: true, label: t('map.go') });

    form.addEventListener('input', (e) => {
        const el = e.target as HTMLInputElement;
        if (el.dataset.param) syncRange(el);
        if (el.dataset.choice === 'type') {
            const parts = cfg.parts[type()] ?? [];
            Object.keys(partColors).forEach((p) => { if (!parts.includes(p)) delete partColors[p]; });
        }
        applyWhen();
        // a change after a map was made means another map: the main action makes it, the old result stays on show
        if (made) stage.go({ run: create, disabled: !place, label: t('map.go') });
        if (el.dataset.text !== 'name') previewSoon();
    });
    form.addEventListener('submit', (e) => e.preventDefault());

    // a stored design opened again ("edit" from the calculator): the same place and settings, the model on the stage
    const reopen = async (uuid: string): Promise<void> => {
        try {
            const file = (await stage.fetchFile(uuid)) as MapFile;
            if (file.tool?.kind !== 'map') return;
            const p = file.tool.params;
            Object.entries(p).forEach(([k, v]) => {
                const num = fieldOf(k); if (num) { num.value = String(v); syncRange(num); }
                const txt = form.querySelector<HTMLInputElement>(`[data-text="${k}"]`); if (txt) { txt.value = String(v ?? ''); txt.dataset.typed = '1'; }
                const flag = form.querySelector<HTMLInputElement>(`[data-flag="${k}"]`); if (flag) flag.checked = Boolean(v);
                const choice = form.querySelector<HTMLInputElement>(`[data-choice="${k}"][value="${String(v)}"]`); if (choice) choice.checked = true;
            });
            Object.entries((p.part_colors as Record<string, { hex?: string }> | undefined) ?? {}).forEach(([part, c]) => { if (c?.hex) partColors[part] = c.hex; });
            applyWhen();
            if (typeof p.lat === 'number' && typeof p.lon === 'number') {
                place = { name: String(p.place ?? ''), kind: '', country: '', display: String(p.place ?? ''), lat: p.lat, lon: p.lon };
                ($('map-place') as HTMLInputElement).value = place.name;
                const chosen = $('map-chosen'); chosen.textContent = t('map.place.chosen', { name: place.name || `${p.lat}, ${p.lon}` }); chosen.classList.remove('hidden');
            }
            if (file.status === 'ready') await showResult(file);
            else if (file.status !== 'failed') { const done = await untilDone(file); if (done.status === 'ready') await showResult(done); }
            previewSoon(0);
        } catch { /* a design that is gone: the page stays empty */ }
    };
    applyWhen();
    if (cfg.from && /^[0-9a-f-]{36}$/.test(cfg.from)) void reopen(cfg.from);
}
