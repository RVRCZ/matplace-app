// A model file read off the main thread: the page keeps breathing, and the upload keeps going, while a 90 MB STL with
// two million triangles is parsed (Roman's dragon sat on "Načítám model…", 24 Oct 2026). STL and OBJ only: three's 3MF
// loader needs the browser's XML parser, which a worker does not have.
import { loadGeometry } from './loaders';
import { stats, GeoStats } from './geometry';

export interface ParsedModel {
    position: Float32Array;
    normal: Float32Array | null;
    stats: GeoStats;
}

export type ParseReply = { ok: true; model: ParsedModel | null } | { ok: false; error: string };

// the worker's own global, typed by hand: the DOM typings of the rest of the bundle must not meet the worker ones
const scope = self as unknown as {
    onmessage: ((e: MessageEvent<File>) => void) | null;
    postMessage(message: ParseReply, transfer?: Transferable[]): void;
};

scope.onmessage = async (e: MessageEvent<File>) => {
    try {
        const geom = await loadGeometry(e.data);
        if (!geom) {
            scope.postMessage({ ok: true, model: null });
            return;
        }
        const plain = geom.index ? geom.toNonIndexed() : geom;
        const position = plain.getAttribute('position').array as Float32Array;
        const n = plain.getAttribute('normal');
        const normal = n ? (n.array as Float32Array) : null;
        const reply: ParseReply = { ok: true, model: { position, normal, stats: stats(plain) } };
        // the arrays move to the page instead of being copied: a copy of 64 MB would take as long as the parse
        scope.postMessage(reply, normal ? [position.buffer, normal.buffer] : [position.buffer]);
    } catch (err) {
        scope.postMessage({ ok: false, error: err instanceof Error ? err.message : String(err) });
    }
};
