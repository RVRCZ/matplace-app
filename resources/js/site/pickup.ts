/**
 * Packeta pickup point picker. A container with data-pickup-box holds hidden fields (data-pickup="id|name|carrier_id|country"),
 * a label (data-pickup="label") and the buttons data-pickup="choose" / "remove". Used by the profile (favourite
 * point) and by the farm order (delivery to a pickup point).
 *
 * data-key           Packeta API key (the picker's public key)
 * data-language      language of the picker
 * data-country       country the points are offered in, or data-country-field = id of a <select> with the country
 *
 * A point of Packeta's own network is sent as its id; a point of a partner carrier (abroad) as the carrier's id
 * plus the carrier's own code of the point, which is what the Packeta API wants for such parcels.
 */
interface PacketaPoint {
    id: string | number; name?: string; place?: string; city?: string; street?: string; zip?: string; country?: string;
    pickupPointType?: string; carrierId?: string | number | null; carrierPickupPointId?: string | number | null;
}
type PacketaWidget = { pick: (key: string, done: (point: PacketaPoint | null) => void, options?: Record<string, unknown>) => void };

const LIBRARY = 'https://widget.packeta.com/v6/www/js/library.js';
let loading: Promise<PacketaWidget | null> | null = null;

function library(): Promise<PacketaWidget | null> {
    const ready = (): PacketaWidget | null => (window as unknown as { Packeta?: { Widget?: PacketaWidget } }).Packeta?.Widget ?? null;
    if (ready()) return Promise.resolve(ready());
    loading ??= new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = LIBRARY;
        script.async = true;
        script.onload = () => resolve(ready());
        script.onerror = () => { loading = null; resolve(null); };
        document.head.appendChild(script);
    });
    return loading;
}

export function describePoint(point: PacketaPoint): { id: string; name: string; carrier_id: string; country: string } {
    const external = point.pickupPointType === 'external' && point.carrierId != null;
    const name = [point.name ?? point.place, point.street, [point.zip, point.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');
    return {
        id: String(external ? (point.carrierPickupPointId ?? point.id) : point.id),
        name: name || String(point.id),
        carrier_id: external ? String(point.carrierId) : '',
        country: String(point.country ?? '').toUpperCase(),
    };
}

export function bootPickup(): void {
    document.querySelectorAll<HTMLElement>('[data-pickup-box]').forEach((box) => {
        const part = <T extends HTMLElement>(name: string) => box.querySelector<T>(`[data-pickup="${name}"]`);
        const fields = ['id', 'name', 'carrier_id', 'country'] as const;
        const set = (values: Record<(typeof fields)[number], string> | null): void => {
            for (const f of fields) {
                const input = part<HTMLInputElement>(f);
                if (input) input.value = values ? values[f] : '';
            }
            const label = part('label');
            if (label) label.textContent = values?.name || (box.dataset.none ?? '');
            part('remove')?.classList.toggle('hidden', !values);
            box.dispatchEvent(new CustomEvent('pickup:change', { detail: values, bubbles: true }));
        };
        const country = (): string => {
            const field = box.dataset.countryField ? (document.getElementById(box.dataset.countryField) as HTMLSelectElement | null) : null;
            return (field?.value || box.dataset.country || 'CZ').toLowerCase();
        };
        part('remove')?.addEventListener('click', () => set(null));
        part('choose')?.addEventListener('click', async () => {
            const widget = await library();
            if (!widget || !box.dataset.key) return;
            widget.pick(box.dataset.key, (point) => { if (point) set(describePoint(point)); }, { language: box.dataset.language ?? 'cs', country: country() });
        });
    });
}
