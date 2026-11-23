/**
 * The two rows of big step buttons of a generated bust (the height of the chest cut, the lean of the head; see
 * resources/views/partials/cut-controls.blade.php). Shared by the calculator's base card and by the bust page, so both
 * mark, show and read them the same way. A lean is previewed in the viewer at once (`onTilt`, degrees against the lean the
 * shown file already has); the server cuts it again when the form is sent.
 */
export interface CutState { cut_controls?: boolean; cut_shift?: number; pitch?: number }

export interface CutControls {
    /** the model can take the controls (its whole figure is kept on the server) */
    shown: boolean;
    /** what to send to POST /api/files/{uuid}/pedestal: nothing when the controls are not offered */
    values(): { cut_shift?: number; pitch?: number };
}

const ON = ['bg-action', 'text-white', 'border-action'];
const OFF = ['bg-white', 'text-slate-700', 'border-slate-300'];

export function bindCutControls(root: ParentNode, ped: CutState | null, onTilt?: (deg: number) => void): CutControls {
    const rows = root.querySelector<HTMLElement>('[data-cut-controls]');
    const shown = Boolean(ped?.cut_controls) && rows !== null;
    rows?.classList.toggle('hidden', !shown);
    rows?.classList.toggle('block', shown);
    const picked = { cut: ped?.cut_shift ?? 0, pitch: ped?.pitch ?? 0 };
    const row = (id: string, key: 'cut' | 'pitch') => {
        const buttons = [...root.querySelectorAll<HTMLButtonElement>(`#${id} button[data-step]`)];
        const mark = () => buttons.forEach((b) => {
            const on = Number(b.dataset.step) === picked[key];
            b.setAttribute('aria-pressed', String(on));
            ON.forEach((c) => b.classList.toggle(c, on));
            OFF.forEach((c) => b.classList.toggle(c, !on));
        });
        // the shown file already leans by ped.pitch: the preview turns it by the difference only
        buttons.forEach((b) => { b.onclick = () => { picked[key] = Number(b.dataset.step); mark(); if (key === 'pitch') onTilt?.(picked.pitch - (ped?.pitch ?? 0)); }; });
        mark();
    };
    row('pedestal-cut', 'cut');
    row('pedestal-pitch', 'pitch');
    return { shown, values: () => (shown ? { cut_shift: picked.cut, pitch: picked.pitch } : {}) };
}
