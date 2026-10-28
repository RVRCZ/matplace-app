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
    viewer.setPieces(parts.pieces);
    parts.colors.forEach((hex, i) => viewer.setPieceColor(i, hex));

    return true;
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
