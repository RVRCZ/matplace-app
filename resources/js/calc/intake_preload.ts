/**
 * A tool's page opened from the home page's router (`/tools/<key>?intake=<token>`, docs/AN.md §22 D): the one hook
 * that fills the page with what the visitor handed in there. The photo is fetched by the token and put into the
 * field marked `data-intake-photo` (the front photo of the bust and the pet, the picture field of the shape tools:
 * charm, coaster, earrings, medallion), as if the visitor had picked it; the module of the page then does what it
 * does for a picked file (a preview, an upload). The parameters go into the fields every module reads: the main size,
 * a text, a kind, the place of a map. Nothing here is a tool's own code: a new tool gets the hand-over by marking its
 * photo field.
 */
export interface IntakePayload {
    token: string;
    tool: string;
    file: string | null;
    name: string | null;
    params: Record<string, unknown>;
    text: string | null;
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

export async function preloadIntake(payload: IntakePayload): Promise<void> {
    const p = payload.params ?? {};
    const size = typeof p.size_mm === 'number' ? p.size_mm : null;
    const text = typeof p.text === 'string' ? p.text : null;
    const place = typeof p.place === 'string' ? p.place : null;
    const kind = typeof p.kind === 'string' ? p.kind : null;

    // the parameters first: the modules read the form on every change, so the photo's upload sees them set
    if (size !== null) {
        setNumber(document.getElementById('figure-size') as HTMLInputElement | null, size);        // the bust, the pet: the height
        setNumber(document.getElementById('relief-w') as HTMLInputElement | null, size);           // the relief, the lithophane: the width
        setNumber(document.querySelector<HTMLInputElement>('[data-param="width"]'), size);          // the shape tools, the picture for the wall, the cutter: the width
        setNumber(document.querySelector<HTMLInputElement>('[data-param="size"]'), size);           // the map: the side
        setNumber(document.querySelector<HTMLInputElement>('[data-param="text_height"]'), size);    // the sign, the standing letters: the letter height
        if (size > 440) setNumber(document.querySelector<HTMLInputElement>('[data-param="head"]'), size / 10);   // the headband: the head's circumference, in cm (440 = a smaller number held at the tool's lowest: not a head)
    }
    if (kind) {
        const radio = document.querySelector<HTMLInputElement>(`input[type=radio][name="kind"][value="${kind}"], input[type=radio][name="style"][value="${kind}"]`);
        if (radio) { radio.checked = true; fire(radio, 'change'); }
        document.querySelector<HTMLButtonElement>(`[data-preset="hb_${kind}"]`)?.click();              // the headband: the costume (the decoration and its colours)
    }
    if (text) {
        setText(document.querySelector<HTMLInputElement>('[data-text="line1"]'), text);              // a name or an inscription of the shape tools
        setText(document.querySelector<HTMLInputElement>('input[name="pedestal_name"]'), text);      // the name on the base of a pet or a bust
    }
    if (place) {
        setText(document.querySelector<HTMLInputElement>('[data-text="name"]'), place);              // the name on the rim of a map
        const search = document.getElementById('map-place') as HTMLInputElement | null;
        if (search) { setText(search, place); (document.getElementById('map-search') as HTMLButtonElement | null)?.click(); }
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
