/**
 * A tool's page opened from the home page (`/tools/<key>?intake=<token>`, docs/AN.md §22 D and §24): one press of
 * "Vytvořit" there ends here, with the wish already in the form. The one hook that fills the page with what the visitor
 * handed in. The photo is fetched by the token and put into the field marked `data-intake-photo` (the front photo of
 * the bust and the pet, the picture field of the shape tools: charm, coaster, earrings, medallion), as if the visitor
 * had picked it; the module of the page then does what it does for a picked file (a preview, an upload). The
 * parameters go into the fields every module reads: the main size, a text, a kind, the place of a map, the number of
 * pieces and the material. A parametric tool takes its own values itself (`values`, param.ts: texts, a shape, a
 * preset, colours), so they are not set twice here. Nothing here is a tool's own code: a new tool gets the hand-over by
 * marking its photo field.
 *
 * `showUnderstood` puts the one quiet line under the tool's name: what the assistant understood, and "I wanted
 * something else", which leads back to the home page with the words. When the tool still needs something only the
 * visitor has (`ask`: a photo, a picture, the place of a map), the line says so in bold: the page then opens asking
 * for it, never with its own sample as if that were the answer (docs/AN.md §25).
 */
export interface IntakePayload {
    token: string;
    tool: string;
    file: string | null;
    name: string | null;
    params: Record<string, unknown>;
    text: string | null;
    values?: Record<string, unknown>;
    understood?: string[];
    unsure?: boolean;
    ask?: { kind: string; text: string; button: string | null } | null;
    back?: string;
    words?: { lead: string; unsure: string; nothing: string; back: string };
}

const fire = (el: HTMLElement, type: string): void => { el.dispatchEvent(new Event(type, { bubbles: true })); };

/** A number put into a range or number field within its own min and max (and step), told to the page. */
function setNumber(input: HTMLInputElement | null, value: number): void {
    if (!input) return;
    const min = Number(input.min); const max = Number(input.max); const step = Number(input.step) || 1;
    let v = value;
    if (!Number.isNaN(min) && input.min !== '') v = Math.max(min, v);
    if (!Number.isNaN(max) && input.max !== '') v = Math.min(max, v);
    if (input.type === 'range' && step > 0) v = Math.round(v / step) * step;
    input.value = String(v);
    fire(input, 'input');
    fire(input, 'change');
}

function setText(input: HTMLInputElement | null, value: string): void {
    if (!input) return;
    input.value = input.maxLength > 0 ? value.slice(0, input.maxLength) : value;
    fire(input, 'input');
    fire(input, 'change');
}

/** The line under the tool's name: "The assistant understood: … · I wanted something else". Built of text nodes: the words are the visitor's own. */
export function showUnderstood(payload: IntakePayload): void {
    const head = document.getElementById('tool-head');
    const w = payload.words;
    if (!head || !w || !payload.back || document.getElementById('tool-intake')) return;
    const line = document.createElement('p');
    line.id = 'tool-intake';
    line.className = 'mt-2 rounded-lg bg-studio px-3 py-2 text-sm leading-snug text-ink';
    line.setAttribute('role', 'note');
    const got = payload.understood ?? [];
    if (payload.unsure) {
        line.append(w.unsure, ' ');
    }
    if (got.length) {
        const lead = document.createElement('span');
        lead.className = 'text-muted';
        lead.textContent = `${w.lead} `;
        const list = document.createElement('span');
        list.dataset.understood = '';
        list.textContent = `${got.join(' · ')}. `;
        line.append(lead, list);
    } else if (!payload.unsure) {
        line.append(w.nothing, ' ');
    }
    const back = document.createElement('a');
    back.href = payload.back;
    back.id = 'tool-intake-back';
    back.className = 'whitespace-nowrap font-semibold text-action-dark underline underline-offset-2';
    back.textContent = w.back;
    line.append(back);
    if (payload.ask) {
        const ask = document.createElement('strong');
        ask.dataset.intakeAsk = payload.ask.kind;
        ask.className = 'mt-1 block font-semibold text-ink';
        ask.textContent = payload.ask.text;
        line.append(ask);
    }
    (document.getElementById('tool-lead') ?? head.lastElementChild ?? head).insertAdjacentElement('afterend', line);
}

export async function preloadIntake(payload: IntakePayload): Promise<void> {
    const p = payload.params ?? {};
    const size = typeof p.size_mm === 'number' ? p.size_mm : null;
    const text = typeof p.text === 'string' ? p.text : null;
    const kind = typeof p.kind === 'string' ? p.kind : null;
    // a parametric tool applied its own values already, as it applies a stored design (param.ts)
    const own = payload.values !== undefined && document.getElementById('tool-page')?.dataset.module === 'param';

    // the parameters first: the modules read the form on every change, so the photo's upload sees them set
    if (size !== null && !own) {
        setNumber(document.getElementById('figure-size') as HTMLInputElement | null, size);        // the bust, the pet: the height
        setNumber(document.getElementById('relief-w') as HTMLInputElement | null, size);           // the relief, the lithophane: the width
        setNumber(document.querySelector<HTMLInputElement>('[data-param="width"]'), size);          // the shape tools, the picture for the wall, the cutter: the width
        setNumber(document.querySelector<HTMLInputElement>('[data-param="size"]'), size);           // the map: the side
        setNumber(document.querySelector<HTMLInputElement>('[data-param="text_height"]'), size);    // the sign, the standing letters: the letter height
    }
    if (kind && !own) {
        const radio = document.querySelector<HTMLInputElement>(`input[type=radio][name="kind"][value="${kind}"], input[type=radio][name="style"][value="${kind}"]`);
        if (radio) { radio.checked = true; fire(radio, 'change'); }
    }
    if (text) {
        if (!own) setText(document.querySelector<HTMLInputElement>('[data-text="line1"]'), text);    // a name or an inscription of the shape tools
        setText(document.querySelector<HTMLInputElement>('input[name="pedestal_name"]'), text);      // the name on the base of a pet or a bust
    }
    // (the place of a map is looked up by the map's own module, which then makes the map: map.ts)
    // the tool waits for something only the visitor has: the field for it comes into view
    if (payload.ask?.kind === 'photo') document.querySelector<HTMLElement>('input[type=file][data-intake-photo]')?.closest('section, form, div')?.scrollIntoView({ block: 'nearest' });
    if (!own) {
        // the last step of every tool's page: how many pieces, which plastic
        const v = payload.values ?? {};
        const qty = document.getElementById('param-qty') as HTMLInputElement | null;
        if (qty && typeof v._qty === 'string' && Number(v._qty) >= 1) setNumber(qty, Number(v._qty));
        const plastic = document.getElementById('param-material') as HTMLSelectElement | null;
        if (plastic && typeof v._material === 'string' && [...plastic.options].some((o) => o.value === v._material)) { plastic.value = v._material; fire(plastic, 'change'); }
    }

    // then the photo, into the field the page marked for it
    const input = document.querySelector<HTMLInputElement>('input[type=file][data-intake-photo]');
    if (!payload.file || !input) return;
    try {
        const res = await fetch(payload.file, { credentials: 'same-origin' });
        if (!res.ok) return;
        const blob = await res.blob();
        const type = blob.type && blob.type.startsWith('image/') ? blob.type : 'image/jpeg';
        const name = (payload.name ?? 'photo.jpg').replace(/\.(heic|heif)$/i, '.jpg');
        const file = new File([blob], name, { type, lastModified: Date.now() });
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        fire(input, 'change');
    } catch {
        // the photo stays where it was; the visitor picks it on the page
    }
}
