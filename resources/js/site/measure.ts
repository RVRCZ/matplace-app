/**
 * Measurement in the browser. Our own statistics are written by the server; the only thing asked of the browser is
 * to say "a page was really shown" (seen(): one small request without any data about the visitor), which is how
 * people are told from robots that never run a script. Google Analytics 4
 * and the Meta pixel are third parties: they are loaded only after the visitor allowed them in the cookie bar, and
 * they hear about the same events the server records:
 *
 *   on a page        window.MP_MEASURE.events (printed by the layout, incl. what a redirect left behind)
 *   after a fetch    the response header X-Matplace-Events
 *   on a click       any element with data-track="download" (and optional data-track-kind, data-track-tool)
 *
 * The choice lives in the cookie `consent` ("a1m0" = analytics yes, marketing no) for 180 days.
 */
interface Fired { type: string; meta?: Record<string, unknown> }
interface MeasureCfg { ga: string | null; pixel: string | null; consent: { analytics: boolean; marketing: boolean } | null; days: number; events: Fired[] }
type Gtag = (...args: unknown[]) => void;
type Fbq = ((...args: unknown[]) => void) & { callMethod?: (...a: unknown[]) => void; queue?: unknown[]; loaded?: boolean; version?: string; push?: unknown };

const w = window as unknown as { MP_MEASURE?: MeasureCfg; dataLayer?: unknown[]; gtag?: Gtag; fbq?: Fbq; _fbq?: Fbq };
const cfg = (): MeasureCfg => w.MP_MEASURE ?? { ga: null, pixel: null, consent: null, days: 180, events: [] };
let choice = cfg().consent;
let gaReady = false;
let pixelReady = false;
const waiting: Fired[] = [];

/** our event → [Google Analytics name, Meta pixel name (standard events are tracked, the rest as custom)] */
const NAMES: Record<string, [string, string, boolean]> = {
    upload: ['upload', 'Upload', false],
    generate: ['generate', 'Generate', false],
    calculation: ['calculation', 'Calculation', false],
    download: ['file_download', 'Download', false],
    order_created: ['begin_checkout', 'InitiateCheckout', true],
    order_paid: ['purchase', 'Purchase', true],
    register: ['sign_up', 'CompleteRegistration', true],
    designer_enabled: ['designer_enabled', 'DesignerEnabled', false],
    designer_file_uploaded: ['designer_file_uploaded', 'DesignerFileUploaded', false],
    ref_visit: ['ref_visit', 'RefVisit', false],
    search: ['search', 'Search', true],
};

function loadScript(src: string): void {
    if (document.querySelector(`script[src="${src}"]`)) return;
    const s = document.createElement('script');
    s.async = true;
    s.src = src;
    document.head.appendChild(s);
}

function startGa(): void {
    const id = cfg().ga;
    if (gaReady || !id || !choice?.analytics) return;
    w.dataLayer = w.dataLayer ?? [];
    // gtag must push the arguments object itself, not an array
    // eslint-disable-next-line prefer-rest-params, @typescript-eslint/no-unused-vars
    w.gtag = w.gtag ?? function gtag(..._args: unknown[]) { w.dataLayer!.push(arguments); };
    w.gtag('js', new Date());
    w.gtag('config', id, { anonymize_ip: true });
    loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`);
    gaReady = true;
}

function startPixel(): void {
    const id = cfg().pixel;
    if (pixelReady || !id || !choice?.marketing) return;
    if (!w.fbq) {
        const fbq: Fbq = function f(...args: unknown[]) { if (fbq.callMethod) fbq.callMethod(...args); else fbq.queue!.push(args); } as Fbq;
        fbq.queue = []; fbq.loaded = true; fbq.version = '2.0';
        w.fbq = fbq; w._fbq = fbq;
        loadScript('https://connect.facebook.net/en_US/fbevents.js');
    }
    w.fbq!('init', id);
    w.fbq!('track', 'PageView');
    pixelReady = true;
}

/** Pass one event on to whoever the visitor allowed. Before a choice exists the event waits; refused, it is dropped. */
export function send(event: Fired): void {
    const names = NAMES[event.type];
    if (!names) return;
    if (choice === null) { waiting.push(event); return; }
    const meta = event.meta ?? {};
    if (gaReady && w.gtag) {
        const params: Record<string, unknown> = { ...meta };
        if (event.type === 'order_paid') params.transaction_id = String(meta.order ?? '');
        w.gtag('event', names[0], params);
    }
    if (pixelReady && w.fbq) {
        const params = event.type === 'order_paid' ? { value: meta.value, currency: meta.currency } : meta;
        // the server reports the same conversion through the Conversions API: one id, counted once
        w.fbq(names[2] ? 'track' : 'trackCustom', names[1], params, meta.event_id ? { eventID: String(meta.event_id) } : undefined);
    }
}

function apply(): void {
    startGa();
    startPixel();
    waiting.splice(0).forEach(send);
}

function store(analytics: boolean, marketing: boolean): void {
    choice = { analytics, marketing };
    document.cookie = `consent=a${analytics ? 1 : 0}m${marketing ? 1 : 0}; path=/; max-age=${cfg().days * 86400}; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
    document.getElementById('consent')?.classList.add('hidden');
    if (!analytics && !marketing) waiting.length = 0;
    apply();
}

function bar(): void {
    const box = document.getElementById('consent');
    if (!box) return;
    const analytics = document.getElementById('consent-analytics') as HTMLInputElement | null;
    const marketing = document.getElementById('consent-marketing') as HTMLInputElement | null;
    const showChoices = (): void => {
        document.getElementById('consent-choices')?.classList.remove('hidden');
        box.querySelector('[data-consent="settings"]')?.classList.add('hidden');
        box.querySelector('[data-consent="save"]')?.classList.remove('hidden');
    };
    box.querySelector('[data-consent="all"]')?.addEventListener('click', () => store(true, true));
    box.querySelector('[data-consent="necessary"]')?.addEventListener('click', () => store(false, false));
    box.querySelector('[data-consent="settings"]')?.addEventListener('click', showChoices);
    box.querySelector('[data-consent="save"]')?.addEventListener('click', () => store(!!analytics?.checked, !!marketing?.checked));
    // "Cookie settings" in the footer and on the cookies page: the same bar again, with the choices open
    document.querySelectorAll('[data-consent-open]').forEach((b) => b.addEventListener('click', () => { box.classList.remove('hidden'); showChoices(); }));
}

/** Answers to fetch carry the events the server recorded while handling them. */
function watchFetch(): void {
    const original = window.fetch.bind(window);
    window.fetch = async (...args: Parameters<typeof fetch>): Promise<Response> => {
        const response = await original(...args);
        try {
            const header = response.headers.get('X-Matplace-Events');
            if (header) (JSON.parse(header) as Fired[]).forEach(send);
        } catch { /* a foreign response or a broken header: nothing to report */ }
        return response;
    };
}

function watchClicks(): void {
    document.addEventListener('click', (e) => {
        const el = (e.target as HTMLElement | null)?.closest<HTMLElement>('[data-track]');
        if (!el?.dataset.track) return;
        const meta: Record<string, unknown> = {};
        if (el.dataset.trackKind) meta.kind = el.dataset.trackKind;
        if (el.dataset.trackTool) meta.tool = el.dataset.trackTool;
        send({ type: el.dataset.track, meta });
    });
}

/** Tell our own statistics that a browser showed this page. No cookie of its own, no consent needed: nothing personal goes. */
function seen(): void {
    if ((navigator as Navigator & { webdriver?: boolean }).webdriver) return;   // an automated browser is a robot too
    const body = new URLSearchParams({ path: location.pathname });
    try {
        if (navigator.sendBeacon?.('/api/seen', body)) return;
    } catch { /* fall through to fetch */ }
    void fetch('/api/seen', { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => undefined);
}

export function bootMeasure(): void {
    seen();
    bar();
    watchFetch();
    watchClicks();
    if (choice !== null) apply();
    cfg().events.forEach(send);
}
