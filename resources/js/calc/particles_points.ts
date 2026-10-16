/**
 * Points of a picture for the particle animation (particles.ts): where the dots of the logo sit and what colour each
 * one has. Pure arithmetic over RGBA pixels, no DOM, so it is tested without a browser (scripts/test_particles.mjs).
 */

/** One dot of the logo: its place as a share of the picture's width and height, its colour, and what it is part of. */
export interface LogoPoint {
    x: number;
    y: number;
    rgb: [number, number, number];
    /** "fill" (the teal of the nozzle and the letters), "line" (their dark outline), "filament" (the orange thread) */
    part: 'fill' | 'line' | 'filament';
    /** along the filament from the nozzle (0) to the letters (1); 0 for the other parts */
    along: number;
}

/** A seeded random generator (mulberry32): the same picture gives the same dots on every load. */
export function seeded(seed: number): () => number {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/** What a pixel of the logo is: the orange thread, the dark outline or the teal fill. */
export function partOf(r: number, g: number, b: number): LogoPoint['part'] {
    if (r > g + 40 && r > b + 50) return 'filament';
    return r + g + b < 260 ? 'line' : 'fill';
}

/**
 * About `count` points of the opaque pixels of a picture (`data`: RGBA, `width` × `height`), spread evenly: the picture
 * is cut into a grid of cells as many as the dots wanted, and every cell whose (slightly shaken) middle is opaque gets a
 * dot. An even grid keeps the edges of the letters sharp where random dots would clump and leave holes. The thin orange
 * thread gets a second dot in every cell, so it reads as a line. Never more than `count` dots; a few less is possible.
 * `parts`: which parts of the picture get dots (the others are left as gaps).
 */
export function samplePoints(data: ArrayLike<number>, width: number, height: number, count: number, seed = 7, parts: LogoPoint['part'][] = ['fill', 'line', 'filament']): LogoPoint[] {
    const rnd = seeded(seed);
    const wanted = (o: number): boolean => data[o + 3] >= 128 && parts.includes(partOf(data[o], data[o + 1], data[o + 2]));
    const opaque = (x: number, y: number): boolean => wanted((y * width + x) * 4);
    let area = 0;
    for (let o = 0; o < data.length; o += 4) if (wanted(o)) area++;
    if (!area || count <= 0) return [];
    // a dot of a honeycomb grid stands for cell × 0.866 cell; the thread's second dots are about one in twenty more
    const cell = Math.max(1, Math.sqrt(area / (count * 0.866 * 0.95)));
    const found: { x: number; y: number; rgb: [number, number, number]; part: LogoPoint['part'] }[] = [];
    const take = (fx: number, fy: number): LogoPoint['part'] | null => {
        const x = Math.min(width - 1, Math.max(0, Math.round(fx))); const y = Math.min(height - 1, Math.max(0, Math.round(fy)));
        if (!opaque(x, y)) return null;
        const o = (y * width + x) * 4;
        const rgb: [number, number, number] = [data[o], data[o + 1], data[o + 2]];
        const part = partOf(rgb[0], rgb[1], rgb[2]);
        found.push({ x, y, rgb, part });
        return part;
    };
    // every other row shifted by half a cell: the dots lie like a honeycomb, which the eye reads as an even surface
    for (let row = 0, cy = cell / 2; cy < height; row++, cy += cell * 0.866) {
        for (let cx = (row % 2 ? cell : cell / 2); cx < width; cx += cell) {
            const part = take(cx + (rnd() - 0.5) * cell * 0.3, cy + (rnd() - 0.5) * cell * 0.3);
            if (part === 'filament') take(cx + cell / 2, cy);
        }
    }
    // too many (the grid is a little finer than the count): random ones go, the rest keeps its order
    while (found.length > count) found.splice(Math.floor(rnd() * found.length), 1);
    // the thread runs down from the nozzle and bends towards the letters: down the picture is along it
    const thread = found.filter((c) => c.part === 'filament');
    const top = thread.length ? Math.min(...thread.map((c) => c.y)) : 0;
    const bottom = thread.length ? Math.max(...thread.map((c) => c.y)) : 0;
    return found.map((c) => ({
        x: (c.x + 0.5) / width,
        y: (c.y + 0.5) / height,
        rgb: c.rgb,
        part: c.part,
        along: c.part === 'filament' && bottom > top ? (c.y - top) / (bottom - top) : 0,
    }));
}
