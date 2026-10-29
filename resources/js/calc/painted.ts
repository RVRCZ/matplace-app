/**
 * Colours painted in a slicer (Bambu Studio, OrcaSlicer, Anycubic Slicer Next: `paint_color`; PrusaSlicer:
 * `mmu_segmentation`), read when the 3MF is uploaded (docs/prompts/import-3mf-barvy.md, docs/AN.md §19). The server
 * keeps the triangles of the preview STL sorted by colour and says where each colour's run starts and ends:
 * `groups: [{hex, from, to}]` (triangle indices, `to` exclusive, the same shape as `parts[].tris` of the colours tool).
 * The viewer then shows every run in its colour as a piece of its own, without a second file. Used by the calculation
 * and, through the same helper, by the order page.
 */
import type { Piece, Viewer } from './viewer';

export interface ColorGroup { hex: string; from: number; to: number }

const HEX = /^#[0-9a-f]{6}$/i;

/**
 * The runs as the viewer's pieces with their colours; null when they do not cover the model exactly once, in order
 * (an old or a broken answer): the model is then shown in one colour as before.
 */
export function piecesFromGroups(groups: ColorGroup[] | null | undefined, triangles: number): { pieces: Piece[]; colors: string[] } | null {
    if (!groups || groups.length < 2 || triangles <= 0) return null;
    let at = 0;
    for (const g of groups) {
        if (!HEX.test(g.hex) || g.from !== at || g.to <= g.from) return null;
        at = g.to;
    }
    if (at !== triangles) return null;

    return {
        pieces: groups.map((g, i) => ({ name: `color_${i + 1}`, tris: [g.from, g.to] as [number, number] })),
        colors: groups.map((g) => g.hex.toLowerCase()),
    };
}

/** Paints the model shown in the viewer by its colour runs; false (and nothing changed) when the runs do not fit it. */
export function paintGroups(viewer: Viewer, groups: ColorGroup[] | null | undefined, triangles: number): boolean {
    const parts = piecesFromGroups(groups, triangles);
    if (!parts) return false;
    paintPieces(viewer, parts.pieces, parts.colors);

    return true;
}

/** The pieces of the shown model (null: the whole model is one) each in its colour; null leaves a piece as it came. */
export function paintPieces(viewer: Viewer, pieces: Piece[] | null, colors: (string | null)[]): void {
    viewer.setPieces(pieces);
    colors.forEach((hex, i) => viewer.setPieceColor(i, hex));
}

interface ToolParams { pieces?: unknown; part_colors?: Record<string, { hex?: string } | null> }

/** The colour a design made by one of our tools gave each of its parts (`tool_params.part_colors`), by part. */
export function toolColors(params: Record<string, unknown> | null | undefined): Record<string, string> {
    const p = (params ?? {}) as ToolParams;
    const out: Record<string, string> = {};
    Object.entries(p.part_colors ?? {}).forEach(([part, c]) => { if (c && HEX.test(c.hex ?? '')) out[part] = c.hex!.toLowerCase(); });
    return out;
}

/**
 * A design made by one of our tools in the colours chosen on its page (docs/AN.md §20): its stored pieces (filament art,
 * an edited model: `tool_params.pieces`, triangle ranges over this very file) each in its part's colour, or the whole
 * model in its one colour (a copper box with its copper lid). null: no colour chosen, or several colours and no ranges
 * over this file (the calculator then asks for the pieces, `tool.pieces_url`).
 */
export function toolPaint(params: Record<string, unknown> | null | undefined, triangles: number): { pieces: Piece[] | null; colors: (string | null)[] } | null {
    const hex = toolColors(params);
    const distinct = [...new Set(Object.values(hex))];
    if (!distinct.length || triangles <= 0) return null;
    const pieces = ranges((params as ToolParams | null | undefined)?.pieces, triangles);
    if (pieces) return { pieces, colors: pieces.map((x) => hex[x.name] ?? (distinct.length === 1 ? distinct[0] : null)) };
    if (distinct.length === 1) return { pieces: null, colors: [distinct[0]] };

    return null;
}

/** Pieces as the server lists them (`X-Pieces`); null for anything that does not cover `triangles` exactly once, in order. */
export function piecesFromHeader(raw: string | null, triangles: number): Piece[] | null {
    try { return ranges(JSON.parse(raw ?? 'null'), triangles); } catch { return null; }
}

/** A list of {name, tris} as pieces of a model of `triangles` triangles: one after another from 0 to the end, else null. */
function ranges(list: unknown, triangles: number): Piece[] | null {
    if (!Array.isArray(list)) return null;
    const out = list.filter((x) => x && typeof x.name === 'string' && Array.isArray(x.tris)).map((x) => ({ name: String(x.name), tris: [Number(x.tris[0]), Number(x.tris[1])] as [number, number] }));
    const ok = out.length > 0 && out.length === list.length && out.every((x, i) => x.tris[0] === (i ? out[i - 1].tris[1] : 0) && x.tris[1] > x.tris[0]) && out[out.length - 1].tris[1] === triangles;
    return ok ? out : null;
}

/** Small dots in the file's colours, for a line of text ("Soubor má 2 barvy"). */
export function colorDots(hexes: string[]): HTMLElement {
    const wrap = document.createElement('span');
    wrap.className = 'inline-flex items-center gap-1 align-middle';
    hexes.filter((h) => HEX.test(h)).forEach((hex) => {
        const dot = document.createElement('span');
        dot.className = 'inline-block h-3.5 w-3.5 rounded-full border border-slate-300';
        dot.style.backgroundColor = hex;
        dot.setAttribute('aria-hidden', 'true');
        wrap.append(dot);
    });

    return wrap;
}
