// Admin photo box: three fixed cameras (top, left, right) on the PC next to the box. The browser remembers which
// camera is which; one click takes a full-resolution still from each and uploads them to the chosen test.

const VIEWS = ['top', 'left', 'right'] as const;
type View = (typeof VIEWS)[number];

const store = {
    get(k: string): string { try { return localStorage.getItem('photobox.' + k) ?? ''; } catch { return ''; } },
    set(k: string, v: string): void { try { localStorage.setItem('photobox.' + k, v); } catch { /* private window: pick again next time */ } },
};

export function bootPhotobox(): void {
    const root = document.getElementById('photobox');
    if (!root) return;
    const msg = document.getElementById('photobox-msg') as HTMLElement;
    const shoot = document.getElementById('photobox-shoot') as HTMLButtonElement;
    const start = document.getElementById('photobox-start') as HTMLButtonElement;
    const streams: Partial<Record<View, MediaStream>> = {};
    const say = (t: string) => { msg.textContent = t; };
    const select = (v: View) => root.querySelector<HTMLSelectElement>(`[data-camera="${v}"]`)!;
    const video = (v: View) => root.querySelector<HTMLVideoElement>(`[data-preview="${v}"]`)!;

    if (!navigator.mediaDevices?.getUserMedia) {
        say('Tenhle prohlížeč nepustí stránku ke kamerám (je potřeba HTTPS a aktuální Chrome nebo Edge).');
        shoot.disabled = true;
        return;
    }

    async function open(v: View): Promise<void> {
        streams[v]?.getTracks().forEach((t) => t.stop());
        delete streams[v];
        const id = select(v).value;
        store.set(v, id);
        const res = root!.querySelector<HTMLElement>(`[data-res="${v}"]`)!;
        video(v).srcObject = null;
        res.textContent = '';
        if (!id) return;
        try {
            // ask for 4K; the camera answers with the most it can do
            const s = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: id }, width: { ideal: 3840 }, height: { ideal: 2160 } }, audio: false });
            streams[v] = s;
            video(v).srcObject = s;
            const st = s.getVideoTracks()[0].getSettings();
            res.textContent = `${st.width ?? '?'} × ${st.height ?? '?'} px`;
        } catch (e) {
            res.textContent = 'Kameru nejde otevřít: ' + (e as Error).message;
        }
    }

    async function list(): Promise<void> {
        // device names are only visible after the first permission
        const probe = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        probe.getTracks().forEach((t) => t.stop());
        const cams = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
        for (const v of VIEWS) {
            const sel = select(v);
            const keep = store.get(v);
            sel.innerHTML = '<option value="">— bez kamery —</option>' + cams.map((c, i) => `<option value="${c.deviceId}">${c.label || 'Kamera ' + (i + 1)}</option>`).join('');
            sel.value = cams.some((c) => c.deviceId === keep) ? keep : '';
            sel.onchange = () => void open(v);
            await open(v);
        }
        say(cams.length ? `Nalezeno kamer: ${cams.length}.` : 'Žádná kamera není připojená.');
        start.hidden = true;
    }

    async function still(v: View): Promise<Blob | null> {
        const s = streams[v];
        if (!s) return null;
        const track = s.getVideoTracks()[0];
        // a real still from the sensor where the browser can take one, else the current video frame
        const IC = (window as unknown as { ImageCapture?: new (t: MediaStreamTrack) => { takePhoto(): Promise<Blob> } }).ImageCapture;
        if (IC) {
            try { return await new IC(track).takePhoto(); } catch { /* fall back to the frame */ }
        }
        const el = video(v);
        const c = document.createElement('canvas');
        c.width = el.videoWidth; c.height = el.videoHeight;
        c.getContext('2d')!.drawImage(el, 0, 0);
        return await new Promise((r) => c.toBlob((b) => r(b), 'image/jpeg', 0.92));
    }

    shoot.addEventListener('click', async () => {
        shoot.disabled = true;
        say('Fotím…');
        try {
            const form = new FormData();
            let n = 0;
            for (const v of VIEWS) {
                const b = await still(v);
                if (!b) continue;
                form.append(`photos[${n}]`, b, `${v}.jpg`);
                form.append(`views[${n}]`, v);
                n++;
            }
            if (!n) { say('Nejdřív vyberte aspoň jednu kameru.'); return; }
            say(`Nahrávám ${n} fotek…`);
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const r = await fetch(root.dataset.upload!, { method: 'POST', body: form, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } });
            const j = await r.json().catch(() => ({ message: 'Server neodpověděl.' }));
            say(j.message ?? (r.ok ? 'Uloženo.' : 'Nepovedlo se.'));
            if (r.ok) setTimeout(() => location.reload(), 800);
        } catch (e) {
            say('Nepovedlo se: ' + (e as Error).message);
        } finally {
            shoot.disabled = false;
        }
    });

    start.addEventListener('click', () => { list().catch((e) => say('Kamery nejsou dostupné: ' + (e as Error).message)); });
    // cameras already allowed once: open them straight away
    navigator.permissions?.query({ name: 'camera' as PermissionName }).then((p) => { if (p.state === 'granted') void list(); }).catch(() => {});
}
