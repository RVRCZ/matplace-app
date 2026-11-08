/**
 * The filament card from an e-shop link (admin; TODO #71, docs/AL.md §21). A [data-shop-load] button next to a
 * [data-shop-url] field posts the link (with the printer and the slot, or the kind, from its data attributes), the
 * state of the job is asked for every 2 s and shown in the box's [data-shop-status], and a finished card opens the
 * one <dialog data-shop-dialog> of the page (resources/views/admin/farm/shop_card.blade.php). The admin corrects the
 * card; "save" posts it back and the page reloads so the forms show what was written.
 */
type Kind = { id: number; label: string; enabled: boolean; manual_only: boolean };
type Settings = { nozzle: number | null; nozzle_first: number | null; bed: number | null; max_volumetric_speed: number | null; drying: string | null; declared: boolean };
type Card = {
    name_cs: string; name_en: string; manufacturer: string; colour_name: string;
    material_id: number | null; material_label: string | null; material_suggestion: string | null;
    hex: string | null; hex_from: string | null; photo: number | null; best_image: number | null;
    settings: Settings | null; print_overrides: Record<string, unknown> | null; test_notes: string;
    net_weight_g: number | null; price: { amount: number; currency: string | null } | null; confidence: number; notes: string; enabled: boolean;
};
type Picture = { n: number; url: string; from: string; alt: string; shows: boolean | null; logo: boolean | null };
type Existing = { id: number; label: string; weight_g: number | null; photo: string | null; hex: string | null };
type Entry = {
    id: string; status: string; message: string | null; url: string; host: string; printer_id: number | null; slot: number | null;
    warnings: string[]; existing: Existing | null; card: Card | null; images: Picture[];
};
type Context = { printerId: number | null; slot: number | null; kindId: number | null; status: HTMLElement | null };

const ID_MARK = '00000000-0000-0000-0000-000000000000';

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url: string, body: Record<string, unknown>): Promise<{ ok: boolean; status: number; data: Record<string, unknown> }> {
    const res = await fetch(url, { method: 'POST', credentials: 'same-origin', body: JSON.stringify(body), headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
    let data: Record<string, unknown> = {};
    try { data = (await res.json()) as Record<string, unknown>; } catch { /* no body */ }
    return { ok: res.ok, status: res.status, data };
}

export function bootFilamentShop(): void {
    const root = document.querySelector<HTMLElement>('[data-filament-shop]');
    if (!root) return;
    const texts = JSON.parse(root.dataset.texts ?? '{}') as Record<string, string>;
    const kinds = JSON.parse(root.dataset.kinds ?? '[]') as Kind[];
    const startUrl = root.dataset.start ?? '';
    const statusUrl = (id: string) => (root.dataset.status ?? '').replace(ID_MARK, id);
    const t = (key: string, vars: Record<string, string> = {}) => Object.entries(vars).reduce((s, [k, v]) => s.split(':' + k).join(v), texts[key] ?? key);
    const dialog = root.querySelector<HTMLDialogElement>('[data-shop-dialog]');
    if (!dialog) return;
    const f = <T extends HTMLElement>(name: string) => dialog.querySelector<T>(`[data-f="${name}"]`);

    let current: Entry | null = null;
    let context: Context = { printerId: null, slot: null, kindId: null, status: null };
    let chosenPhoto: number | null = null;

    // ── the link → the job ───────────────────────────────────────────────────
    document.addEventListener('click', (e) => {
        const button = e.target instanceof Element ? e.target.closest<HTMLElement>('[data-shop-load]') : null;
        if (!button) return;
        e.preventDefault();
        const box = button.closest<HTMLElement>('[data-shop]');
        const input = box?.querySelector<HTMLInputElement>('[data-shop-url]');
        const status = box?.querySelector<HTMLElement>('[data-shop-status]') ?? (box?.nextElementSibling instanceof HTMLElement && box.nextElementSibling.matches('[data-shop-status]') ? box.nextElementSibling : null);
        const url = input?.value.trim() ?? '';
        const say = (text: string, bad = false) => {
            if (!status) return;
            status.textContent = text;
            status.classList.remove('hidden');
            status.classList.toggle('text-red-700', bad);
        };
        if (!url) { say(t('url_missing'), true); input?.focus(); return; }
        context = {
            printerId: button.dataset.printer ? Number(button.dataset.printer) : null,
            slot: button.dataset.slot !== undefined && button.dataset.slot !== '' ? Number(button.dataset.slot) : null,
            kindId: button.dataset.kind ? Number(button.dataset.kind) : null,
            status,
        };
        button.setAttribute('disabled', '');
        say(t('status.queued'));
        void (async () => {
            try {
                const body: Record<string, unknown> = { url };
                if (context.printerId !== null) body.printer_id = context.printerId;
                if (context.slot !== null) body.slot = context.slot;
                const r = await postJson(startUrl, body);
                if (!r.ok) { say(String(r.data.message ?? t('failed')), true); return; }
                await follow(r.data as unknown as Entry, say);
            } catch {
                say(t('failed'), true);
            } finally {
                button.removeAttribute('disabled');
            }
        })();
    });

    async function follow(first: Entry, say: (text: string, bad?: boolean) => void): Promise<void> {
        let entry = first;
        for (let i = 0; i < 150 && entry.status !== 'done' && entry.status !== 'failed'; i++) {
            say(t('status.' + entry.status));
            await new Promise((resolve) => setTimeout(resolve, 2000));
            const res = await fetch(statusUrl(entry.id), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!res.ok) { say(t('failed'), true); return; }
            entry = (await res.json()) as Entry;
        }
        if (entry.status === 'failed') { say(entry.message ?? t('status.failed'), true); return; }
        if (entry.status !== 'done') { say(t('status.failed'), true); return; }
        if (!entry.card && !entry.existing) {
            // nothing to show: a listing page, or the agent could not tell what it is
            say(entry.message ?? (entry.warnings.map((w) => t('warn.' + w)).join(' ') || t('status.failed')), true);
            return;
        }
        say(t('status.done'));
        open(entry);
    }

    // ── the card ─────────────────────────────────────────────────────────────
    function open(entry: Entry): void {
        current = entry;
        const card = entry.card;
        const existing = entry.existing;
        chosenPhoto = card?.photo ?? null;
        const set = (name: string, value: string | number | null | undefined) => { const el = f<HTMLInputElement | HTMLTextAreaElement>(name); if (el) el.value = value === null || value === undefined ? '' : String(value); };
        const text = (name: string, value: string) => { const el = f(name); if (el) el.textContent = value; };
        const show = (name: string, on: boolean) => f(name)?.classList.toggle('hidden', !on);

        text('url', entry.url);
        const warnings = f<HTMLUListElement>('warnings');
        if (warnings) {
            warnings.replaceChildren(...entry.warnings.map((w) => { const li = document.createElement('li'); li.textContent = '⚠ ' + t('warn.' + w); return li; }));
        }
        show('existing', existing !== null);
        show('fields', existing === null);
        show('lead', existing === null);
        const error = f('error');
        if (error) { error.textContent = ''; error.classList.add('hidden'); }
        const place = dialog!.querySelector<HTMLButtonElement>('[data-act="place"]');
        const color = dialog!.querySelector<HTMLButtonElement>('[data-act="color"]');
        const hasPosition = entry.printer_id !== null && entry.slot !== null;
        if (place) {
            place.classList.toggle('hidden', !hasPosition);
            place.textContent = existing ? place.dataset.labelExisting ?? '' : place.dataset.labelNew ?? '';
        }
        color?.classList.toggle('hidden', existing !== null);

        if (existing) {
            text('existing_label', t('existing', { color: existing.label }));
            // the grams field is needed with the existing colour too: it lives among the fields, so it is shown alone
            show('fields', hasPosition);
            dialog!.querySelectorAll<HTMLElement>('[data-f="fields"] > div').forEach((col, i) => col.classList.toggle('hidden', i === 0));
            dialog!.querySelectorAll<HTMLElement>('[data-f="fields"] > div:last-child > *').forEach((el) => el.classList.toggle('hidden', !el.querySelector('[data-f="remaining_g"]')));
            set('remaining_g', existing.weight_g ?? 1000);
        } else if (card) {
            dialog!.querySelectorAll<HTMLElement>('[data-f="fields"] > div, [data-f="fields"] > div:last-child > *').forEach((el) => el.classList.remove('hidden'));
            set('name_cs', card.name_cs);
            set('name_en', card.name_en);
            set('manufacturer', card.manufacturer);
            text('colour_name', card.colour_name ? '„' + card.colour_name + '“' : '');
            const select = f<HTMLSelectElement>('material');
            if (select) {
                const none = document.createElement('option');
                none.value = '';
                none.textContent = t('field.kind_none');
                select.replaceChildren(none, ...kinds.map((k) => { const o = document.createElement('option'); o.value = String(k.id); o.textContent = k.label + (k.enabled ? '' : ' (vypnuto)'); return o; }));
                select.value = card.material_id !== null ? String(card.material_id) : (context.kindId !== null && card.material_suggestion === null ? String(context.kindId) : '');
            }
            text('suggestion', card.material_suggestion ? t('field.suggestion', { text: card.material_suggestion }) : '');
            set('hex', card.hex ?? '');
            const pick = f<HTMLInputElement>('hex_pick');
            if (pick) pick.value = card.hex ?? '#cccccc';
            text('hex_from', card.hex_from ? t('hex_from.' + card.hex_from) : '');
            set('remaining_g', card.net_weight_g ?? 1000);
            text('weight', card.net_weight_g ? '(' + card.net_weight_g + ' g netto)' : '');
            const s = card.settings;
            const settingsText = s && s.declared
                ? [s.nozzle ? t('field.nozzle') + ' ' + s.nozzle + (s.nozzle_first && s.nozzle_first !== s.nozzle ? '/' + s.nozzle_first : '') + ' °C' : '', s.bed ? t('field.bed') + ' ' + s.bed + ' °C' : '', s.max_volumetric_speed ? t('flow') + ' ' + s.max_volumetric_speed + ' mm³/s' : '', s.drying ? t('field.drying') + ' ' + s.drying : ''].filter(Boolean).join(' · ')
                : t('field.settings_none');
            text('settings_text', settingsText);
            const saveSettings = f<HTMLInputElement>('save_settings');
            if (saveSettings) { saveSettings.checked = !!card.print_overrides; saveSettings.disabled = !card.print_overrides; }
            set('test_notes', card.test_notes);
            text('price', card.price ? '(' + card.price.amount.toLocaleString('cs-CZ') + ' ' + (card.price.currency ?? '') + ')' : '');
            text('agent_notes', [card.notes, t('field.confidence') + ' ' + Math.round(card.confidence * 100) + ' %'].filter(Boolean).join(' · '));
            const enabled = f<HTMLInputElement>('enabled');
            if (enabled) enabled.checked = card.enabled;
            paintPhoto(entry);
        }
        if (!dialog!.open) dialog!.showModal();
    }

    function paintPhoto(entry: Entry): void {
        const img = f<HTMLImageElement>('photo');
        const none = f('photo_none');
        const use = f<HTMLInputElement>('use_photo');
        const pic = chosenPhoto !== null ? entry.images.find((p) => p.n === chosenPhoto) ?? null : null;
        if (img) { img.classList.toggle('hidden', !pic); if (pic) img.src = pic.url; }
        none?.classList.toggle('hidden', !!pic);
        if (use) { use.checked = pic !== null && pic.logo !== true && pic.shows !== false; use.disabled = !pic; }
        const thumbs = f('thumbs');
        if (thumbs) {
            thumbs.replaceChildren(...entry.images.map((p) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'relative h-12 w-12 overflow-hidden rounded-lg border ' + (p.n === chosenPhoto ? 'border-action ring-2 ring-action' : 'border-slate-200');
                b.title = p.alt || p.from;
                const im = document.createElement('img');
                im.src = p.url;
                im.alt = '';
                im.className = 'h-full w-full object-cover';
                b.appendChild(im);
                if (p.logo === true || p.shows === false) {
                    const mark = document.createElement('span');
                    mark.className = 'absolute right-0 top-0 rounded-bl bg-white/90 px-1 text-[10px] text-amber-700';
                    mark.textContent = p.logo === true ? '⚑' : '✕';
                    b.appendChild(mark);
                }
                b.addEventListener('click', () => { chosenPhoto = p.n; paintPhoto(entry); });
                return b;
            }));
        }
    }

    f<HTMLInputElement>('hex_pick')?.addEventListener('input', (e) => { const v = (e.target as HTMLInputElement).value; const hex = f<HTMLInputElement>('hex'); if (hex) hex.value = v; });
    f<HTMLInputElement>('hex')?.addEventListener('input', (e) => { const v = (e.target as HTMLInputElement).value; if (/^#[0-9a-fA-F]{6}$/.test(v)) { const pick = f<HTMLInputElement>('hex_pick'); if (pick) pick.value = v.toLowerCase(); } });

    // ── the buttons ──────────────────────────────────────────────────────────
    dialog.addEventListener('click', (e) => {
        const button = e.target instanceof Element ? e.target.closest<HTMLButtonElement>('[data-act]') : null;
        if (!button || !current) return;
        e.preventDefault();
        const act = button.dataset.act;
        if (act === 'cancel') {
            void postJson(statusUrl(current.id) + '/cancel', {});
            dialog.close();
            context.status?.classList.add('hidden');
            return;
        }
        if (act !== 'place' && act !== 'color') return;
        const value = (name: string) => f<HTMLInputElement | HTMLTextAreaElement>(name)?.value ?? '';
        const checked = (name: string) => f<HTMLInputElement>(name)?.checked ?? false;
        const body: Record<string, unknown> = {
            mode: act, name: value('name_cs'), name_en: value('name_en'), farm_material_id: value('material') || null, manufacturer: value('manufacturer'),
            hex: value('hex') || null, use_photo: checked('use_photo'), photo: chosenPhoto, save_settings: checked('save_settings'), enabled: checked('enabled'),
            test_notes: value('test_notes'), remaining_g: value('remaining_g') || 0,
        };
        const error = f('error');
        dialog.querySelectorAll<HTMLButtonElement>('[data-act]').forEach((b) => b.setAttribute('disabled', ''));
        if (error) { error.textContent = t('saving'); error.classList.remove('hidden', 'text-red-700'); }
        void (async () => {
            const r = await postJson(statusUrl(current!.id) + '/save', body);
            dialog.querySelectorAll<HTMLButtonElement>('[data-act]').forEach((b) => b.removeAttribute('disabled'));
            if (!r.ok) {
                if (error) { error.textContent = String(r.data.message ?? t('failed')); error.classList.add('text-red-700'); error.classList.remove('hidden'); }
                return;
            }
            const color = r.data.color as { id: number; label: string; kind_id: number } | undefined;
            if (error) { error.textContent = t('saved', { color: color?.label ?? '' }); error.classList.remove('text-red-700'); }
            const notices = Array.isArray(r.data.notices) ? (r.data.notices as string[]) : [];
            setTimeout(() => {
                dialog.close();
                if (notices.length) alert(notices.join('\n'));
                const slot = r.data.slot as { printer_id: number } | null | undefined;
                location.hash = slot ? '' : 'kind-' + String(color?.kind_id ?? '');
                location.reload();
            }, 900);
        })();
    });
}
