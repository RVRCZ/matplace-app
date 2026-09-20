export interface FileInfo {
    uuid: string;
    name: string;
    ext: string;
    status: string;
    error: string | null;
    bbox: { x: number; y: number; z: number } | null;
    volume_mm3: number | null;
    area_mm2: number | null;
    issues: string[];
    stl_url: string | null;
    kind?: string;
    hints?: { supports?: boolean; infill?: number; quality?: string };
    generation?: { token: string; refinable: boolean } | null;
}

export interface CalcInfo {
    token: string;
    url: string;
    status: string;
    error: string | null;
    params: Record<string, unknown>;
    rough: { grams: number; minutes: number; price_min: number; price_max: number; prices: unknown[] } | null;
    slicer: { grams: number; minutes: number; dims: { x: number; y: number; z: number }; supports_used: boolean; warnings: string[] } | null;
    prices: { profile: string; label: string | null; total: number; lead_time_days: number; unit: { material: number; time: number; royalty: number }; setup: number; quantity: number }[] | null;
    file: FileInfo | null;
}

const routes = () => (window as unknown as { MP_ROUTES: Record<string, string> }).MP_ROUTES;

async function json<T>(res: Response): Promise<T> {
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = (body as { message?: string }).message ?? `HTTP ${res.status}`;
        throw new Error(msg);
    }
    return body as T;
}

export async function uploadFile(file: File, geometry?: { volume_mm3: number; area_mm2: number }, onProgress?: (pct: number) => void): Promise<FileInfo> {
    const form = new FormData();
    form.append('file', file, file.name);
    if (geometry) {
        form.append('volume_mm3', String(geometry.volume_mm3));
        form.append('area_mm2', String(geometry.area_mm2));
    }
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', routes().uploads);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.withCredentials = true;
        xhr.upload.onprogress = (e) => { if (e.lengthComputable && onProgress) onProgress(Math.round((e.loaded / e.total) * 100)); };
        xhr.onload = () => {
            try {
                const body = JSON.parse(xhr.responseText || '{}');
                if (xhr.status >= 200 && xhr.status < 300) resolve(body.file as FileInfo);
                else reject(new Error(body.message ?? `HTTP ${xhr.status}`));
            } catch (e) { reject(e); }
        };
        xhr.onerror = () => reject(new Error('network'));
        xhr.send(form);
    });
}

export async function createCalculation(payload: Record<string, unknown>): Promise<CalcInfo> {
    const res = await fetch(routes().calculations, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
    });
    return (await json<{ calculation: CalcInfo }>(res)).calculation;
}

export async function getCalculation(token: string): Promise<CalcInfo> {
    const res = await fetch(`${routes().calcShow}/${token}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    return (await json<{ calculation: CalcInfo }>(res)).calculation;
}

export async function getFile(uuid: string): Promise<FileInfo> {
    const res = await fetch(`${routes().files}/${uuid}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    return (await json<{ file: FileInfo }>(res)).file;
}
