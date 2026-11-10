export const CLIP_LIMITS: Record<string, number> = { removal: 10, turn: 10, detail: 3, with_source: 3, in_hand: 3 };

type Capture = {
    getUserMedia: (constraints: MediaStreamConstraints) => Promise<MediaStream>;
    enumerateDevices: () => Promise<MediaDeviceInfo[]>;
};

type Recording = { blob: Blob; mime: string };

export async function availableDevices(capture: Capture, kind: 'videoinput' | 'audioinput'): Promise<MediaDeviceInfo[]> {
    return (await capture.enumerateDevices()).filter((device) => device.kind === kind);
}

export function rewindPreview(video: Pick<HTMLVideoElement, 'currentTime' | 'play'>): void {
    video.currentTime = 0;
    video.play().catch(() => undefined);
}

export async function recordClip(kind: string, camera = '', microphone = '', capture?: Capture): Promise<Recording> {
    const media = capture ?? navigator.mediaDevices;
    if (!media?.getUserMedia || typeof MediaRecorder === 'undefined') {
        throw new Error('MediaRecorder is not available');
    }
    const stream = await media.getUserMedia({
        video: {
            width: { ideal: 1080 }, height: { ideal: 1920 }, facingMode: { ideal: 'environment' },
            ...(camera ? { deviceId: { exact: camera } } : {}),
        },
        audio: microphone ? { deviceId: { exact: microphone } } : true,
    });
    const preferred = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4']
        .find((type) => MediaRecorder.isTypeSupported(type));
    const recorder = new MediaRecorder(stream, preferred ? { mimeType: preferred } : undefined);
    const chunks: Blob[] = [];
    recorder.ondataavailable = (event) => { if (event.data.size) chunks.push(event.data); };

    return new Promise<Recording>((resolve, reject) => {
        const finish = () => stream.getTracks().forEach((track) => track.stop());
        recorder.onerror = () => { finish(); reject(new Error('Recording failed')); };
        recorder.onstop = () => {
            finish();
            const mime = recorder.mimeType || preferred || 'video/webm';
            resolve({ blob: new Blob(chunks, { type: mime }), mime });
        };
        recorder.start(250);
        setTimeout(() => { if (recorder.state !== 'inactive') recorder.stop(); }, (CLIP_LIMITS[kind] ?? 3) * 1000);
    });
}

function button(text: string, className = 'btn-quiet'): HTMLButtonElement {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = `${className} text-sm`;
    element.textContent = text;
    return element;
}

async function openRecorder(root: HTMLElement): Promise<void> {
    const kind = root.dataset.kind ?? '';
    const limit = CLIP_LIMITS[kind] ?? 3;
    const panel = root.querySelector<HTMLElement>('[data-recorder-panel]');
    if (!panel) return;
    panel.hidden = false;
    panel.replaceChildren();
    const camera = document.createElement('select');
    const microphone = document.createElement('select');
    camera.setAttribute('aria-label', root.dataset.cameraLabel ?? 'Kamera');
    microphone.setAttribute('aria-label', root.dataset.microphoneLabel ?? 'Mikrofon');
    panel.append(camera, microphone);
    const status = document.createElement('p');
    status.className = 'text-xs text-slate-600';
    panel.append(status);

    let stream: MediaStream | null = null;
    try {
        // Ask once before listing: browsers otherwise hide device labels and the rear camera.
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: true });
        stream.getTracks().forEach((track) => track.stop());
        for (const [select, type, fallback] of [[camera, 'videoinput', root.dataset.defaultCamera ?? 'Výchozí / zadní kamera'], [microphone, 'audioinput', root.dataset.defaultMicrophone ?? 'Výchozí mikrofon']] as const) {
            select.append(new Option(fallback, ''));
            (await availableDevices(navigator.mediaDevices, type)).forEach((device, index) => select.append(new Option(device.label || `${type === 'videoinput' ? 'Kamera' : 'Mikrofon'} ${index + 1}`, device.deviceId)));
        }
    } catch {
        // recordClip reports a useful failure if even the default devices cannot be opened.
    }

    const start = button(root.dataset.startLabel ?? 'Spustit natáčení', 'btn-primary');
    const again = button(root.dataset.againLabel ?? 'Natočit znovu');
    const upload = button(root.dataset.uploadLabel ?? 'Uložit klip', 'btn-primary');
    const preview = document.createElement('video');
    preview.controls = true;
    preview.playsInline = true;
    preview.className = 'mt-2 w-full rounded-xl';
    preview.hidden = true;
    const rewind = button(root.dataset.rewindLabel ?? 'Přehrát od začátku');
    panel.append(start, again, upload, rewind, preview);
    again.hidden = upload.hidden = rewind.hidden = true;
    let url = '';
    let recording: Recording | null = null;

    start.onclick = async () => {
        start.disabled = true;
        status.textContent = (root.dataset.guide ?? '') + ` · ${limit} s`;
        preview.hidden = true;
        again.hidden = upload.hidden = rewind.hidden = true;
        try {
            let left = limit;
            const countdown = setInterval(() => { status.textContent = `${root.dataset.remainingLabel ?? 'Zbývá'} ${--left} s`; }, 1000);
            recording = await recordClip(kind, camera.value, microphone.value);
            clearInterval(countdown);
            if (url) URL.revokeObjectURL(url);
            url = URL.createObjectURL(recording.blob);
            preview.src = url;
            preview.hidden = false;
            again.hidden = upload.hidden = rewind.hidden = false;
            status.textContent = root.dataset.readyLabel ?? 'Klip je připravený ke kontrole.';
            preview.play().catch(() => undefined);
        } catch {
            status.textContent = root.dataset.errorLabel ?? 'Kameru nebo mikrofon se nepodařilo spustit. Zkuste Nahrát soubor.';
        } finally {
            start.disabled = false;
        }
    };
    again.onclick = () => { preview.pause(); start.click(); };
    rewind.onclick = () => rewindPreview(preview);
    upload.onclick = async () => {
        if (!recording) return;
        upload.disabled = true;
        const data = new FormData();
        const extension = recording.mime.includes('mp4') ? 'mp4' : 'webm';
        data.append('clip', recording.blob, `${kind}.${extension}`);
        data.append('_token', document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '');
        try {
            const response = await fetch(root.dataset.uploadUrl ?? '', { method: 'POST', body: data, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Upload failed');
            window.location.reload();
        } catch {
            status.textContent = root.dataset.uploadErrorLabel ?? 'Uložení se nepovedlo, zkuste to znovu.';
            upload.disabled = false;
        }
    };
}

export function bootClipRecorders(): void {
    document.querySelectorAll<HTMLElement>('[data-clip-recorder]').forEach((root) => {
        root.querySelector<HTMLButtonElement>('[data-record]')?.addEventListener('click', () => openRecorder(root));
    });
}
