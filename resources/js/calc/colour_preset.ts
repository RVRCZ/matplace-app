/**
 * The first choice of colours on a fresh order (Roman, 21 Nov 2026): the set of colours nearest to the design's is
 * ticked, the first colour is the member of it nearest to the plate's colour, and every layer or part gets the nearest
 * colour of that set. Pure arithmetic on hex colours so it can be tested without a page; farm.ts calls it once, when
 * the order is opened. Nothing here ever runs again for an order the customer has touched.
 */
export interface SetMember { id: number; hex: string }

const rgb = (hex: string): [number, number, number] => {
    const h = hex.replace('#', '');
    const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
    return [parseInt(full.slice(0, 2), 16) || 0, parseInt(full.slice(2, 4), 16) || 0, parseInt(full.slice(4, 6), 16) || 0];
};
export const colourGap = (a: string, b: string): number => { const x = rgb(a); const y = rgb(b); return Math.hypot(x[0] - y[0], x[1] - y[1], x[2] - y[2]); };

/** The set whose colours lie nearest, in sum, to all the colours the design wants (the same measure the order page sorts its sets by). */
export function nearestSetIndex(sets: SetMember[][], wanted: string[]): number {
    let best = -1; let bestGap = Infinity;
    sets.forEach((members, i) => {
        const gap = wanted.reduce((sum, h) => sum + Math.min(...members.map((a) => colourGap(a.hex, h))), 0);
        if (gap < bestGap - 1e-9 || (Math.abs(gap - bestGap) <= 1e-9 && members.length > (sets[best]?.length ?? 0))) { best = i; bestGap = gap; }
    });
    return best;
}

/**
 * @param openers  the members of the set that can open the print (enough filament on the machine)
 * @param members  every member of the set
 * @param rows     the colours the design was made in, one per layer change or part (after the first colour)
 * @param byParts  parts may share a colour; layers may not repeat the one before them (that would be no change)
 */
export function presetColours(openers: SetMember[], members: SetMember[], plateHex: string, rows: (string | null)[], byParts: boolean): { main: number; ids: number[] } {
    const nearest = (list: SetMember[], hex: string): SetMember[] => [...list].sort((a, b) => colourGap(a.hex, hex) - colourGap(b.hex, hex));
    const main = nearest(openers, plateHex)[0].id;
    const ids: number[] = [];
    rows.forEach((hex, i) => {
        const ranked = nearest(members, hex ?? plateHex);
        const before = i === 0 ? main : ids[i - 1];
        ids.push((byParts ? ranked[0] : ranked.find((a) => a.id !== before) ?? ranked[0]).id);
    });
    return { main, ids };
}
