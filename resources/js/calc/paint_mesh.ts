/**
 * The mesh the colouring tool works on (docs/AS.md): the triangles of a model's STL welded into shared vertices, with
 * the neighbour across every edge, and one state per triangle (0 = not painted, n = the n-th colour of the palette).
 * Nothing here knows about three.js or the page, so the same code runs in the browser and in scripts/check_paint_mesh.mjs.
 *
 *   flood   a tap fills the patch around a triangle: on while the neighbour has the colour the tapped triangle had
 *           and the surface does not bend by more than an angle (or the whole connected body)
 *   brush   a stroke paints what lies within a ball's reach of its dabs on the surface; the triangles its rim crosses
 *           are refined first, by splitting their longest edge at its middle, both triangles of the edge at once. The
 *           mesh therefore never gets a T-junction and stays the very same surface: what is sent to the server is a
 *           mesh a slicer takes.
 *   steps   every change is journalled (begin … end) as a step that can be undone and done again; the refinement
 *           itself stays (it changes no shape), a step only puts the states back
 *   record  what the server gets: a state per source triangle, 255 where a triangle was refined, and the refined
 *           triangles themselves (engines/python/paint_tool.py)
 */
export const REPLACED = 255;

export interface Step { tris: Uint32Array; before: Uint8Array; after: Uint8Array }
export interface PaintRecordMesh { triangles: number; states: Uint8Array; added: Float32Array; addedStates: Uint8Array }

const pow2 = (n: number): number => { let p = 1024; while (p < n) p *= 2; return p; };

export class PaintMesh {
    pos: Float32Array;            // vertices, three numbers each
    tri: Uint32Array;             // three vertex indices per triangle
    adj: Int32Array;              // per triangle and edge k (from corner k to k + 1): the triangle across it, -1 for none
    state: Uint8Array;
    nV = 0;
    nT = 0;
    readonly srcT: number;        // triangles of the source file; slots 0 … srcT - 1 are its triangles (or what is left of them)
    private refined: Uint8Array;  // per source triangle: the brush has split it
    /** Triangles whose state or shape changed since the page last took them (the page redraws just these). */
    dirty: number[] = [];
    /** Triangles whose shape changed (the halves of a split): a part of `dirty`. */
    dirtyGeom: number[] = [];
    private mark: Int32Array;
    private must: Uint8Array;     // per triangle: it waits for a later dab of the stroke, or is a piece of one that did
    private origin: Int32Array;   // per triangle made after the epoch: the triangle of the epoch it was split from
    private epochT = 0;
    private stamp = 0;
    // the journal of the step being made
    private jOn = false;
    private jId = 0;
    private jSeen: Int32Array;
    private jPos: Int32Array;
    private jT: number[] = [];
    private jBefore: number[] = [];

    private constructor(pos: Float32Array, nV: number, tri: Uint32Array, nT: number) {
        this.pos = pos; this.nV = nV; this.tri = tri; this.nT = nT; this.srcT = nT;
        this.adj = new Int32Array(nT * 3).fill(-1);
        this.state = new Uint8Array(nT);
        this.refined = new Uint8Array(nT);
        this.mark = new Int32Array(nT);
        this.must = new Uint8Array(nT);
        this.origin = new Int32Array(nT);
        this.epochT = nT;
        this.jSeen = new Int32Array(nT);
        this.jPos = new Int32Array(nT);
    }

    /** From the triangles of an STL (nine numbers each): the corners at the very same place become one vertex. */
    static fromSoup(soup: Float32Array): PaintMesh {
        const nT = Math.floor(soup.length / 9);
        const bits = new Uint32Array(soup.buffer, soup.byteOffset, nT * 9);
        const size = pow2(nT * 4);
        const mask = size - 1;
        const table = new Int32Array(size).fill(-1);
        const pos = new Float32Array(nT * 9);
        const tri = new Uint32Array(nT * 3);
        let nV = 0;
        for (let c = 0; c < nT * 3; c++) {
            // -0 and 0 are one place
            const x = bits[c * 3] === 0x80000000 ? 0 : bits[c * 3]; const y = bits[c * 3 + 1] === 0x80000000 ? 0 : bits[c * 3 + 1]; const z = bits[c * 3 + 2] === 0x80000000 ? 0 : bits[c * 3 + 2];
            let h = (Math.imul(x, 73856093) ^ Math.imul(y, 19349663) ^ Math.imul(z, 83492791)) & mask;
            let v = -1;
            for (;;) {
                const at = table[h];
                if (at < 0) break;
                if (pos[at * 3] === soup[c * 3] && pos[at * 3 + 1] === soup[c * 3 + 1] && pos[at * 3 + 2] === soup[c * 3 + 2]) { v = at; break; }
                h = (h + 1) & mask;
            }
            if (v < 0) {
                v = nV++;
                pos[v * 3] = soup[c * 3]; pos[v * 3 + 1] = soup[c * 3 + 1]; pos[v * 3 + 2] = soup[c * 3 + 2];
                table[h] = v;
            }
            tri[c] = v;
        }
        const mesh = new PaintMesh(pos.slice(0, Math.max(3, nV * 3 + 3 * 1024)), nV, tri, nT);
        mesh.link();
        return mesh;
    }

    /** The neighbour across every edge: the triangle that has the same edge the other way round (else the same way). */
    private link(): void {
        const { tri, adj, nT } = this;
        const size = pow2(nT * 6);
        const mask = size - 1;
        const table = new Int32Array(size).fill(-1);
        const hash = (a: number, b: number): number => (Math.imul(a, 0x9e3779b1) ^ Math.imul(b, 0x85ebca6b)) & mask;
        for (let e = 0; e < nT * 3; e++) {
            const t = (e / 3) | 0; const k = e - t * 3;
            let h = hash(tri[e], tri[t * 3 + (k + 1) % 3]);
            while (table[h] >= 0) h = (h + 1) & mask;
            table[h] = e;
        }
        const find = (a: number, b: number, not: number): number => {
            let h = hash(a, b);
            for (;;) {
                const e = table[h];
                if (e < 0) return -1;
                const t = (e / 3) | 0; const k = e - t * 3;
                if (t !== not && adj[e] < 0 && tri[e] === a && tri[t * 3 + (k + 1) % 3] === b) return e;
                h = (h + 1) & mask;
            }
        };
        for (let pass = 0; pass < 2; pass++) {
            for (let e = 0; e < nT * 3; e++) {
                if (adj[e] >= 0) continue;
                const t = (e / 3) | 0; const k = e - t * 3;
                const a = tri[e]; const b = tri[t * 3 + (k + 1) % 3];
                if (a === b) continue;
                // the first pass pairs edges that run opposite ways (a sound mesh), the second what is left the same way (a mesh wound badly)
                const twin = pass === 0 ? find(b, a, t) : find(a, b, t);
                if (twin >= 0) { adj[e] = (twin / 3) | 0; adj[twin] = t; }
            }
        }
    }

    private grow(): void {
        if ((this.nT + 4) * 3 > this.tri.length) {
            const cap = Math.ceil((this.nT + 4) * 1.5) + 4096;
            const copy = <T extends Uint32Array | Int32Array | Uint8Array>(old: T, make: (n: number) => T, per: number, fill?: number): T => { const a = make(cap * per); if (fill !== undefined) a.fill(fill); a.set(old); return a; };
            this.tri = copy(this.tri, (n) => new Uint32Array(n), 3);
            this.adj = copy(this.adj, (n) => new Int32Array(n), 3, -1);
            this.state = copy(this.state, (n) => new Uint8Array(n), 1);
            this.mark = copy(this.mark, (n) => new Int32Array(n), 1);
            this.must = copy(this.must, (n) => new Uint8Array(n), 1);
            this.origin = copy(this.origin, (n) => new Int32Array(n), 1);
            this.jSeen = copy(this.jSeen, (n) => new Int32Array(n), 1);
            this.jPos = copy(this.jPos, (n) => new Int32Array(n), 1);
        }
        if ((this.nV + 2) * 3 > this.pos.length) {
            const a = new Float32Array(Math.ceil((this.nV + 2) * 1.5) * 3 + 3 * 4096);
            a.set(this.pos);
            this.pos = a;
        }
    }

    // ── geometry ──────────────────────────────────────────────────────────────────────────────────

    /** The nine numbers of a triangle into `out` at `at`. */
    corners(t: number, out: Float32Array | number[], at = 0): void {
        const { pos, tri } = this;
        for (let k = 0; k < 3; k++) { const v = tri[t * 3 + k] * 3; out[at + k * 3] = pos[v]; out[at + k * 3 + 1] = pos[v + 1]; out[at + k * 3 + 2] = pos[v + 2]; }
    }

    /** The normal of a triangle (not normalised: its length is twice the area). */
    private normal(t: number, out: number[]): void {
        const { pos, tri } = this;
        const a = tri[t * 3] * 3; const b = tri[t * 3 + 1] * 3; const c = tri[t * 3 + 2] * 3;
        const ux = pos[b] - pos[a]; const uy = pos[b + 1] - pos[a + 1]; const uz = pos[b + 2] - pos[a + 2];
        const vx = pos[c] - pos[a]; const vy = pos[c + 1] - pos[a + 1]; const vz = pos[c + 2] - pos[a + 2];
        out[0] = uy * vz - uz * vy; out[1] = uz * vx - ux * vz; out[2] = ux * vy - uy * vx;
    }

    area(t: number): number {
        const n = [0, 0, 0];
        this.normal(t, n);
        return 0.5 * Math.hypot(n[0], n[1], n[2]);
    }

    /** Where a ray meets a triangle: the distance along it, or -1 (both faces count: a mesh may be wound inside out). */
    rayHit(t: number, o: ArrayLike<number>, d: ArrayLike<number>): number {
        const { pos, tri } = this;
        const a = tri[t * 3] * 3; const b = tri[t * 3 + 1] * 3; const c = tri[t * 3 + 2] * 3;
        const e1x = pos[b] - pos[a]; const e1y = pos[b + 1] - pos[a + 1]; const e1z = pos[b + 2] - pos[a + 2];
        const e2x = pos[c] - pos[a]; const e2y = pos[c + 1] - pos[a + 1]; const e2z = pos[c + 2] - pos[a + 2];
        const px = d[1] * e2z - d[2] * e2y; const py = d[2] * e2x - d[0] * e2z; const pz = d[0] * e2y - d[1] * e2x;
        const det = e1x * px + e1y * py + e1z * pz;
        if (Math.abs(det) < 1e-12) return -1;
        const inv = 1 / det;
        const tx = o[0] - pos[a]; const ty = o[1] - pos[a + 1]; const tz = o[2] - pos[a + 2];
        const u = (tx * px + ty * py + tz * pz) * inv;
        if (u < -1e-6 || u > 1 + 1e-6) return -1;
        const qx = ty * e1z - tz * e1y; const qy = tz * e1x - tx * e1z; const qz = tx * e1y - ty * e1x;
        const v = (d[0] * qx + d[1] * qy + d[2] * qz) * inv;
        if (v < -1e-6 || u + v > 1 + 1e-6) return -1;
        const dist = (e2x * qx + e2y * qy + e2z * qz) * inv;
        return dist > 0 ? dist : -1;
    }

    /**
     * The page has just drawn its picture of the triangles' numbers: from now on every triangle remembers which
     * triangle of that picture it was split from (hitNear looks among those first).
     */
    markEpoch(): void { this.epochT = this.nT; }

    /** How many triangles were made since the picture of the numbers was drawn. */
    sinceEpoch(): number { return this.nT - this.epochT; }

    /**
     * The triangle a ray hits, given the triangle a picture of the model named at that place. Since the picture was
     * drawn the brush may have split that triangle: the hit is then one of the pieces it was split into, looked for
     * among them alone (they are joined edge to edge). Only when none of them is hit (a pixel at the model's outline)
     * the triangles around are looked through, `reach` of them, and the nearest hit wins. [triangle, distance] or null.
     */
    hitNear(guess: number, o: ArrayLike<number>, d: ArrayLike<number>, reach = 300): [number, number] | null {
        if (guess < 0 || guess >= this.nT) return null;
        const family = guess < this.epochT ? guess : this.origin[guess];
        let id = ++this.stamp;
        let queue = [guess];
        this.mark[guess] = id;
        let best: [number, number] | null = null;
        for (let i = 0; i < queue.length; i++) {
            const t = queue[i];
            const dist = this.rayHit(t, o, d);
            if (dist > 0 && (!best || dist < best[1])) best = [t, dist];
            for (let k = 0; k < 3; k++) {
                const u = this.adj[t * 3 + k];
                if (u < 0 || this.mark[u] === id || (u !== family && !(u >= this.epochT && this.origin[u] === family))) continue;
                this.mark[u] = id; queue.push(u);
            }
        }
        if (best) return best;
        id = ++this.stamp;
        queue = [guess];
        this.mark[guess] = id;
        for (let i = 0; i < queue.length && i < reach; i++) {
            const t = queue[i];
            const dist = this.rayHit(t, o, d);
            if (dist > 0 && (!best || dist < best[1])) best = [t, dist];
            for (let k = 0; k < 3; k++) { const u = this.adj[t * 3 + k]; if (u >= 0 && this.mark[u] !== id) { this.mark[u] = id; queue.push(u); } }
        }
        return best;
    }

    /** The square of the distance from a point to a triangle. */
    private dist2(t: number, px: number, py: number, pz: number): number {
        const { pos, tri } = this;
        const a = tri[t * 3] * 3; const b = tri[t * 3 + 1] * 3; const c = tri[t * 3 + 2] * 3;
        const ax = pos[a]; const ay = pos[a + 1]; const az = pos[a + 2];
        const abx = pos[b] - ax; const aby = pos[b + 1] - ay; const abz = pos[b + 2] - az;
        const acx = pos[c] - ax; const acy = pos[c + 1] - ay; const acz = pos[c + 2] - az;
        const apx = px - ax; const apy = py - ay; const apz = pz - az;
        const d1 = abx * apx + aby * apy + abz * apz; const d2 = acx * apx + acy * apy + acz * apz;
        let s: number; let w: number;      // the nearest point is a + ab * s + ac * w
        if (d1 <= 0 && d2 <= 0) { s = 0; w = 0; } else {
            const bpx = apx - abx; const bpy = apy - aby; const bpz = apz - abz;
            const d3 = abx * bpx + aby * bpy + abz * bpz; const d4 = acx * bpx + acy * bpy + acz * bpz;
            if (d3 >= 0 && d4 <= d3) { s = 1; w = 0; } else {
                const vc = d1 * d4 - d3 * d2;
                if (vc <= 0 && d1 >= 0 && d3 <= 0) { s = d1 / (d1 - d3 || 1); w = 0; } else {
                    const cpx = apx - acx; const cpy = apy - acy; const cpz = apz - acz;
                    const d5 = abx * cpx + aby * cpy + abz * cpz; const d6 = acx * cpx + acy * cpy + acz * cpz;
                    if (d6 >= 0 && d5 <= d6) { s = 0; w = 1; } else {
                        const vb = d5 * d2 - d1 * d6;
                        if (vb <= 0 && d2 >= 0 && d6 <= 0) { s = 0; w = d2 / (d2 - d6 || 1); } else {
                            const va = d3 * d6 - d5 * d4;
                            if (va <= 0 && d4 - d3 >= 0 && d5 - d6 >= 0) { w = (d4 - d3) / ((d4 - d3) + (d5 - d6) || 1); s = 1 - w; } else {
                                const den = 1 / (va + vb + vc || 1);
                                s = vb * den; w = vc * den;
                            }
                        }
                    }
                }
            }
        }
        const dx = apx - abx * s - acx * w; const dy = apy - aby * s - acy * w; const dz = apz - abz * s - acz * w;
        return dx * dx + dy * dy + dz * dz;
    }

    // ── the journal ───────────────────────────────────────────────────────────────────────────────

    /** Starts a step: every change until end() belongs to it. */
    begin(): void {
        this.jOn = true; this.jId++; this.jT = []; this.jBefore = [];
    }

    /** Ends the step; null when nothing changed. */
    end(): Step | null {
        if (!this.jOn) return null;
        this.jOn = false;
        const keep: number[] = [];
        for (let i = 0; i < this.jT.length; i++) if (this.state[this.jT[i]] !== this.jBefore[i]) keep.push(i);
        if (!keep.length) return null;
        const step: Step = { tris: new Uint32Array(keep.length), before: new Uint8Array(keep.length), after: new Uint8Array(keep.length) };
        keep.forEach((i, n) => { step.tris[n] = this.jT[i]; step.before[n] = this.jBefore[i]; step.after[n] = this.state[this.jT[i]]; });
        return step;
    }

    private note(t: number, before: number): void {
        if (!this.jOn || this.jSeen[t] === this.jId) return;
        this.jSeen[t] = this.jId; this.jPos[t] = this.jT.length;
        this.jT.push(t); this.jBefore.push(before);
    }

    private set(t: number, s: number): void {
        if (this.state[t] === s) return;
        this.note(t, this.state[t]);
        this.state[t] = s;
        this.dirty.push(t);
    }

    undo(step: Step): void { for (let i = 0; i < step.tris.length; i++) { this.state[step.tris[i]] = step.before[i]; this.dirty.push(step.tris[i]); } }

    redo(step: Step): void { for (let i = 0; i < step.tris.length; i++) { this.state[step.tris[i]] = step.after[i]; this.dirty.push(step.tris[i]); } }

    // ── painting ──────────────────────────────────────────────────────────────────────────────────

    /** Every triangle in one state (the model's own colour again: 0). */
    fillAll(s: number): void { for (let t = 0; t < this.nT; t++) this.set(t, s); }

    /** Every triangle of one state into another (a colour taken out of the palette). */
    replaceState(from: number, to: number): void { for (let t = 0; t < this.nT; t++) if (this.state[t] === from) this.set(t, to); }

    /** Triangle ranges in a state each: a file that came painted (`groups` of the upload's analysis). */
    setRanges(ranges: { from: number; to: number; state: number }[]): void {
        ranges.forEach((r) => { for (let t = Math.max(0, r.from); t < Math.min(this.nT, r.to); t++) this.state[t] = r.state; });
    }

    /**
     * Fills the patch around `seed` with state `s`: on across an edge while the neighbour has the state the seed had and
     * the two faces meet at no more than `angle` degrees (180: the whole connected body of that state). Returns how many.
     */
    flood(seed: number, angle: number, s: number): number {
        if (seed < 0 || seed >= this.nT) return 0;
        const from = this.state[seed];
        if (from === s) return 0;
        const limit = angle >= 180 ? -2 : Math.cos(angle * Math.PI / 180);
        const id = ++this.stamp;
        const queue = [seed];
        this.mark[seed] = id;
        const a = [0, 0, 0]; const b = [0, 0, 0];
        let n = 0;
        for (let i = 0; i < queue.length; i++) {
            const t = queue[i];
            this.set(t, s); n++;
            if (limit > -2) this.normal(t, a);
            const la = Math.hypot(a[0], a[1], a[2]);
            for (let k = 0; k < 3; k++) {
                const u = this.adj[t * 3 + k];
                if (u < 0 || this.mark[u] === id || this.state[u] !== from) continue;
                if (limit > -2) {
                    this.normal(u, b);
                    const lb = Math.hypot(b[0], b[1], b[2]);
                    // a sliver without an area has no direction: it goes with its neighbour
                    if (la > 0 && lb > 0 && (a[0] * b[0] + a[1] * b[1] + a[2] * b[2]) / (la * lb) < limit) continue;
                }
                this.mark[u] = id;
                queue.push(u);
            }
        }
        return n;
    }

    /**
     * Splits edge k of triangle t at its middle (or at the share `f` of the way from corner k to the next), and the
     * triangle across it with it. The slot t keeps the piece at corner k, the other piece is a new triangle; both
     * inherit the state. Returns the new vertex.
     */
    private splitEdge(t: number, k: number, f = 0.5): number {
        this.grow();
        const { pos } = this;
        const v0 = this.tri[t * 3 + k]; const v1 = this.tri[t * 3 + (k + 1) % 3];
        const m = this.nV++;
        for (let i = 0; i < 3; i++) pos[m * 3 + i] = Math.fround(pos[v0 * 3 + i] + (pos[v1 * 3 + i] - pos[v0 * 3 + i]) * f);
        const u = this.adj[t * 3 + k];
        const halves = this.halve(t, k, m);
        if (u >= 0 && u !== t) {
            let j = -1;
            for (let i = 0; i < 3; i++) { const a = this.tri[u * 3 + i]; const b = this.tri[u * 3 + (i + 1) % 3]; if ((a === v1 && b === v0) || (a === v0 && b === v1)) { j = i; break; } }
            if (j >= 0) {
                const reversed = this.tri[u * 3 + j] === v1;
                const other = this.halve(u, j, m);
                // the halves that share the corner v0 face each other, and so do those at v1
                const atV0 = reversed ? other[1] : other[0]; const atV1 = reversed ? other[0] : other[1];
                this.adj[halves[0] * 3] = atV0; this.adj[atV0 * 3] = halves[0];
                this.adj[halves[1] * 3] = atV1; this.adj[atV1 * 3] = halves[1];
            }
        }
        return m;
    }

    /** The longest edge of a triangle: [its index, the square of its length]. */
    private longest(t: number): [number, number] {
        const { pos, tri } = this;
        const a = tri[t * 3] * 3; const b = tri[t * 3 + 1] * 3; const c = tri[t * 3 + 2] * 3;
        const len = (x: number, y: number): number => (pos[x] - pos[y]) ** 2 + (pos[x + 1] - pos[y + 1]) ** 2 + (pos[x + 2] - pos[y + 2]) ** 2;
        const l0 = len(a, b); const l1 = len(b, c); const l2 = len(c, a);
        return l0 >= l1 && l0 >= l2 ? [0, l0] : l1 >= l2 ? [1, l1] : [2, l2];
    }

    /**
     * Splits a triangle at the middle of its longest edge, the way that keeps the triangles well shaped (Rivara): when
     * that edge is not the longest edge of the triangle across it, that one is split first, by its own longest edge,
     * and so on; otherwise a neighbour would be cut into slivers, and slivers take many more cuts to get small.
     */
    private splitLongest(t: number): void {
        for (let guard = 0; guard < 64; guard++) {
            const [k, len] = this.longest(t);
            const u = this.adj[t * 3 + k];
            if (u < 0 || u === t || this.longest(u)[1] <= len * (1 + 1e-9)) { this.splitEdge(t, k); return; }
            const depth = this.chain;
            if (depth > 48) { this.splitEdge(t, k); return; }
            this.chain = depth + 1;
            this.splitLongest(u);
            this.chain = depth;
        }
        this.splitEdge(t, this.longest(t)[0]);
    }

    private chain = 0;

    /** One triangle cut from the middle m of its edge k to the corner opposite: [the half in its slot, the new half]. */
    private halve(t: number, k: number, m: number): [number, number] {
        const { tri, adj } = this;
        const v0 = tri[t * 3 + k]; const v1 = tri[t * 3 + (k + 1) % 3]; const v2 = tri[t * 3 + (k + 2) % 3];
        const n1 = adj[t * 3 + (k + 1) % 3]; const n2 = adj[t * 3 + (k + 2) % 3];
        const b = this.nT++;
        tri[t * 3] = v0; tri[t * 3 + 1] = m; tri[t * 3 + 2] = v2;
        adj[t * 3] = -1; adj[t * 3 + 1] = b; adj[t * 3 + 2] = n2;
        tri[b * 3] = m; tri[b * 3 + 1] = v1; tri[b * 3 + 2] = v2;
        adj[b * 3] = -1; adj[b * 3 + 1] = n1; adj[b * 3 + 2] = t;
        if (n1 >= 0) {
            for (let i = 0; i < 3; i++) {
                if (adj[n1 * 3 + i] !== t) continue;
                const p = tri[n1 * 3 + i]; const q = tri[n1 * 3 + (i + 1) % 3];
                if ((p === v1 && q === v2) || (p === v2 && q === v1)) { adj[n1 * 3 + i] = b; break; }
            }
        }
        // the new half has the state of the whole, and the state the whole had before this step
        this.state[b] = this.state[t];
        // both halves are new shapes: whoever was walking the mesh looks at them again
        this.mark[t] = 0; this.mark[b] = 0; this.jSeen[b] = 0;
        this.origin[b] = t < this.epochT ? t : this.origin[t];
        this.must[b] = this.must[t];
        if (this.jOn) this.note(b, this.jSeen[t] === this.jId ? this.jBefore[this.jPos[t]] : this.state[t]);
        if (t < this.srcT) this.refined[t] = 1;
        this.dirty.push(t, b);
        this.dirtyGeom.push(t, b);
        return [t, b];
    }

    /**
     * A stroke of the brush begins: a ball of `r` mm that leaves state `s` where it goes. What it paints is everything
     * on the surface within `r` of the line through its dabs' middles (a dab that knows its way, `dir`, is joined to
     * the one before it; one that does not stands alone): the sides of a stroke are straight, not scalloped.
     */
    strokeStart(r: number, s: number): void {
        this.sk = { r, r2: r * r, s, grid: new Map(), c: [], linked: [], pending: [] };
    }

    /**
     * One dab of the stroke at the point `p` of the surface, on or near the triangle `seed`. A triangle wholly inside
     * the stroke is painted as it is; one the stroke's rim crosses is split (splitLongest, while its longest edge is
     * longer than `edge`) and then cut where the rim crosses its edges, so the rim is a line through points that lie
     * on it, in pieces no longer than `edge`. No triangle is made beyond `maxT` in all: then a triangle goes by its
     * middle, the rim stays as coarse as the mesh is, and the answer is false.
     *
     * Two things keep a stroke from refining its own inside. A triangle that already has the colour is left alone,
     * wherever the rim crosses it. And with `dir` (the way the stroke is going, a unit vector) a small triangle (no edge
     * longer than `r`) that sticks out of the stroke only ahead of the dab's middle waits: the next dabs swallow it whole
     * or meet it with their side. What waits is looked at again at every dab, so nothing is forgotten; a dab without
     * `dir` (a sharp turn) and strokeEnd() settle all that waits.
     */
    strokeDab(seed: number, p: ArrayLike<number>, edge: number, maxT: number, dir: ArrayLike<number> | null = null): boolean {
        const k = this.sk;
        if (!k || seed < 0 || seed >= this.nT) return true;
        const at = k.c.length / 3;
        k.c.push(p[0], p[1], p[2]);
        k.linked.push(dir !== null && at > 0);
        // cells of two radii: a point within r of a dab, or of the line to the dab before it, finds them in the cells around its own
        const size = 2 * k.r;
        const key = this.cellKey(Math.floor(p[0] / size), Math.floor(p[1] / size), Math.floor(p[2] / size));
        const cell = k.grid.get(key);
        if (cell) cell.push(at); else k.grid.set(key, [at]);
        return this.settle([seed, ...k.pending], p, edge, maxT, dir);
    }

    /** The stroke ends: what waited for a dab to come is settled where the stroke stopped. */
    strokeEnd(): void {
        const k = this.sk;
        if (k && k.pending.length && k.c.length) this.settle([...k.pending], k.c.slice(-3), 0, 0, null, true);
        if (k) for (const t of k.pending) this.must[t] = 0;
        this.sk = null;
    }

    /** One dab on its own: a ball of `r` at `p` (see strokeDab). */
    brush(seed: number, p: ArrayLike<number>, r: number, s: number, edge: number, maxT: number): boolean {
        this.strokeStart(r, s);
        const whole = this.strokeDab(seed, p, edge, maxT);
        this.strokeEnd();
        return whole;
    }

    private sk: { r: number; r2: number; s: number; grid: Map<number, number[]>; c: number[]; linked: boolean[]; pending: number[] } | null = null;

    private cellKey(x: number, y: number, z: number): number { return (Math.imul(x, 73856093) ^ Math.imul(y, 19349663) ^ Math.imul(z, 83492791)) | 0; }

    /**
     * How deep a point lies in the stroke: the brush's radius less the distance to the nearest dab's middle or to the
     * line from a dab to the one before it (mm; above zero inside, below outside, far outside a large negative number).
     */
    private depth(x: number, y: number, z: number): number {
        const k = this.sk!; const c = k.c;
        const size = 2 * k.r;
        const ix = Math.floor(x / size); const iy = Math.floor(y / size); const iz = Math.floor(z / size);
        let best = Infinity;
        for (let dx = -1; dx <= 1; dx++) for (let dy = -1; dy <= 1; dy++) for (let dz = -1; dz <= 1; dz++) {
            const list = k.grid.get(this.cellKey(ix + dx, iy + dy, iz + dz));
            if (!list) continue;
            for (const i of list) {
                const ax = x - c[i * 3]; const ay = y - c[i * 3 + 1]; const az = z - c[i * 3 + 2];
                let d = ax * ax + ay * ay + az * az;
                if (k.linked[i]) {
                    // the nearest point of the line from this dab back to the one before it
                    const bx = c[i * 3 - 3] - c[i * 3]; const by = c[i * 3 - 2] - c[i * 3 + 1]; const bz = c[i * 3 - 1] - c[i * 3 + 2];
                    const f = (ax * bx + ay * by + az * bz) / (bx * bx + by * by + bz * bz || 1);
                    if (f > 0 && f < 1) { const ex = ax - bx * f; const ey = ay - by * f; const ez = az - bz * f; d = ex * ex + ey * ey + ez * ez; }
                }
                if (d < best) best = d;
            }
        }
        return best === Infinity ? -1e9 : k.r - Math.sqrt(best);
    }

    /**
     * Where the stroke's rim crosses an edge whose ends lie on its two sides: the share of the way from the vertex at
     * `a` to the one at `b` (offsets into pos), found by halving; the point returned is on the rim or a hair inside.
     */
    private rimAt(a: number, b: number, aOutside: boolean): number {
        const pos = this.pos;
        let inside = aOutside ? 1 : 0; let outside = aOutside ? 0 : 1;
        for (let i = 0; i < 14; i++) {
            const f = (inside + outside) / 2;
            if (this.depth(pos[a] + (pos[b] - pos[a]) * f, pos[a + 1] + (pos[b + 1] - pos[a + 1]) * f, pos[a + 2] + (pos[b + 2] - pos[a + 2]) * f) >= 0) inside = f; else outside = f;
        }
        return inside;
    }

    /** Does a triangle reach into the stroke at all (any dab; asked only for the few triangles that wait). */
    private touchesStroke(t: number): boolean {
        const k = this.sk!;
        for (let i = 0; i < k.c.length; i += 3) if (this.dist2(t, k.c[i], k.c[i + 1], k.c[i + 2]) <= k.r2) return true;
        return false;
    }

    /**
     * The work of a dab: the triangles within reach of `p`, and those that waited (`must`), are painted, split or left
     * waiting. `closing`: the stroke is over, with the fineness and the limit of its last dab.
     *
     * A corner counts as inside or outside the stroke only when it is so by more than a hair (`eps`); a corner on the
     * rim is neither. A triangle is cut only between a corner clearly inside and one clearly outside, and the point
     * of the cut lies on the rim: so no cut can ask for another one beside it, however the rims of two dabs graze
     * each other (they did, and the brush split one spot down to nothing until it ran out of triangles).
     */
    private settle(stack: number[], p: ArrayLike<number>, edge: number, maxT: number, dir: ArrayLike<number> | null, closing = false): boolean {
        const k = this.sk!;
        const s = k.s; const r2 = k.r2;
        if (closing) { edge = this.lastEdge; maxT = this.lastMax; } else { this.lastEdge = edge; this.lastMax = maxT; }
        const e2 = edge * edge;
        const eps = Math.min(0.02, Math.max(0.002, 0.02 * edge));
        const deep = (k.r - eps) * (k.r - eps);
        // a dab joined to the one before it reaches as far as the line between them does: a little beyond the two balls
        const joined = !closing && k.linked[k.linked.length - 1] === true;
        const reach = joined ? r2 * 1.07 : r2;
        const id = ++this.stamp;
        const waits: number[] = [];
        let whole = true;
        let budget = 200000;     // no dab ever needs this many looks: a guard against a loop nobody thought of
        while (stack.length && budget-- > 0) {
            const t = stack.pop()!;
            if (this.mark[t] === id) continue;
            let d2 = this.dist2(t, p[0], p[1], p[2]);
            if (joined && d2 > r2) d2 = Math.min(d2, this.dist2(t, k.c[k.c.length - 6], k.c[k.c.length - 5], k.c[k.c.length - 4]));
            if (d2 > reach) {
                // out of this dab's reach: only what waited, or a piece of it, is still looked at, while it reaches into the stroke
                if (!this.must[t]) continue;
                if (!this.touchesStroke(t)) { this.must[t] = 0; continue; }
                d2 = 0;
            }
            this.mark[t] = id;
            this.must[t] = 0;
            const pos = this.pos; const tri = this.tri;
            const a = tri[t * 3] * 3; const b = tri[t * 3 + 1] * 3; const c = tri[t * 3 + 2] * 3;
            const da = this.depth(pos[a], pos[a + 1], pos[a + 2]); const db = this.depth(pos[b], pos[b + 1], pos[b + 2]); const dc = this.depth(pos[c], pos[c + 1], pos[c + 2]);
            const oa = da < -eps; const ob = db < -eps; const oc = dc < -eps;
            const hasIn = da > eps || db > eps || dc > eps;
            if (!oa && !ob && !oc) {
                // no corner outside: the stroke's own; three corners on the rim go by their middle
                if (hasIn || this.depth((pos[a] + pos[b] + pos[c]) / 3, (pos[a + 1] + pos[b + 1] + pos[c + 1]) / 3, (pos[a + 2] + pos[b + 2] + pos[c + 2]) / 3) >= 0) this.set(t, s);
            } else if (this.state[t] !== s && (hasIn || d2 < deep)) {
                // the rim crosses it
                const len2 = this.longest(t)[1];
                const ahead = (v: number): boolean => (pos[v] - p[0]) * dir![0] + (pos[v + 1] - p[1]) * dir![1] + (pos[v + 2] - p[2]) * dir![2] > 0;
                if (dir && len2 <= r2 && (!oa || ahead(a)) && (!ob || ahead(b)) && (!oc || ahead(c))) {
                    // sticks out only ahead of the stroke: a later dab's business
                    this.must[t] = 1;
                    waits.push(t);
                } else if (len2 > e2 && this.nT + 100 <= maxT) {
                    // the halves, and whatever else the split touched, are looked at again, each on its own
                    const from = this.dirtyGeom.length;
                    this.must[t] = 1;      // its pieces are looked at wherever they lie
                    this.splitLongest(t);
                    for (let i = from; i < this.dirtyGeom.length; i++) stack.push(this.dirtyGeom[i]);
                    continue;
                } else {
                    if (len2 > e2) whole = false;
                    // fine enough: the triangle is cut where the stroke's rim crosses its edges, so the rim is a clean line
                    // through points that lie on it (not the zigzag of whole triangles in or out)
                    if (this.nT + 100 <= maxT) {
                        const depths = [da, db, dc];
                        let cut = false;
                        for (let e = 0; e < 3 && !cut; e++) {
                            const d0 = depths[e]; const d1 = depths[(e + 1) % 3];
                            if (!((d0 > eps && d1 < -eps) || (d0 < -eps && d1 > eps))) continue;
                            const f = this.rimAt(tri[t * 3 + e] * 3, tri[t * 3 + (e + 1) % 3] * 3, d0 < 0);
                            // a crossing right at a corner is that corner: no sliver is cut off for it
                            if (f < 0.12 || f > 0.88) continue;
                            const from = this.dirtyGeom.length;
                            this.must[t] = 1;
                            this.splitEdge(t, e, f);
                            for (let i = from; i < this.dirtyGeom.length; i++) stack.push(this.dirtyGeom[i]);
                            cut = true;
                        }
                        if (cut) continue;
                    }
                    // nothing left to cut: the triangle goes with its middle
                    if (this.depth((pos[a] + pos[b] + pos[c]) / 3, (pos[a + 1] + pos[b + 1] + pos[c + 1]) / 3, (pos[a + 2] + pos[b + 2] + pos[c + 2]) / 3) >= 0) this.set(t, s);
                }
            }
            for (let i = 0; i < 3; i++) { const u = this.adj[t * 3 + i]; if (u >= 0 && this.mark[u] !== id) stack.push(u); }
        }
        if (stack.length) whole = false;
        k.pending = waits;
        return whole;
    }

    private lastEdge = 0.4;
    private lastMax = 0;

    // ── what the page shows and sends ─────────────────────────────────────────────────────────────

    /** The area in each state (mm²), index = state. */
    areas(): Float64Array {
        const out = new Float64Array(256);
        for (let t = 0; t < this.nT; t++) out[this.state[t]] += this.area(t);
        return out;
    }

    /**
     * How many times a print of this colouring changes its filament: in every layer one change for each colour beyond
     * the first that the layer holds (the same count the server makes of a painted 3MF, PaintedColors::extra). A
     * triangle that is not painted counts in the colour of each band it reaches into. `layer` in mm.
     */
    colourChanges(bands: { z: number; color: number }[], layer = 0.2): number {
        const [lo, hi] = this.heightRange();
        if (!(hi > lo)) return 0;
        const nl = Math.min(50000, Math.max(1, Math.ceil((hi - lo) / layer)));
        const at = (z: number): number => Math.min(nl - 1, Math.max(0, Math.floor((z - lo) / layer)));
        const diff: (Int32Array | undefined)[] = [];
        const add = (s: number, a: number, b: number): void => { const d = diff[s] ?? (diff[s] = new Int32Array(nl + 1)); d[a]++; d[b + 1]--; };
        const cuts = bands.map((b) => lo + b.z);
        const { pos, tri } = this;
        for (let t = 0; t < this.nT; t++) {
            const za = pos[tri[t * 3] * 3 + 2]; const zb = pos[tri[t * 3 + 1] * 3 + 2]; const zc = pos[tri[t * 3 + 2] * 3 + 2];
            const zmin = Math.min(za, zb, zc); const zmax = Math.max(za, zb, zc);
            const s = this.state[t];
            if (s !== 0 || !cuts.length) { add(s, at(zmin), at(zmax)); continue; }
            // below the first band the model's own colour, then band by band
            if (zmin < cuts[0]) add(0, at(zmin), at(Math.min(zmax, cuts[0] - 1e-6)));
            for (let i = 0; i < cuts.length; i++) {
                const from = Math.max(zmin, cuts[i]); const to = i + 1 < cuts.length ? Math.min(zmax, cuts[i + 1] - 1e-6) : zmax;
                if (to >= from) add(bands[i].color, at(from), at(to));
            }
        }
        const colours = new Int32Array(nl);
        const which = new Int32Array(nl);       // the sum of the states a layer holds: the state itself where there is one
        diff.forEach((d, s) => { if (!d) return; let run = 0; for (let i = 0; i < nl; i++) { run += d[i]; if (run > 0) { colours[i]++; which[i] += s; } } });
        let changes = 0;
        for (let i = 0; i < nl; i++) {
            if (colours[i] > 1) changes += colours[i] - 1;
            // colours one above another: a layer in one colour on a layer in another one is a change too
            else if (i > 0 && colours[i] === 1 && colours[i - 1] === 1 && which[i] !== which[i - 1]) changes++;
        }
        return changes;
    }

    /** The lowest and the highest z of the model. */
    heightRange(): [number, number] {
        let lo = Infinity; let hi = -Infinity;
        for (let v = 0; v < this.nV; v++) { const z = this.pos[v * 3 + 2]; if (z < lo) lo = z; if (z > hi) hi = z; }
        return [lo, hi];
    }

    /** How many triangles the brush has added so far. */
    added(): number { return this.nT - this.srcT; }

    /**
     * What the server builds the painted file from (engines/python/paint_tool.py): a state per source triangle, 255 where
     * the brush refined it, and the refined triangles themselves (nine numbers each) with their states.
     */
    record(): PaintRecordMesh {
        const states = new Uint8Array(this.srcT);
        const changed: number[] = [];
        for (let t = 0; t < this.srcT; t++) {
            if (this.refined[t]) { changed.push(t); states[t] = REPLACED; } else states[t] = this.state[t];
        }
        const n = changed.length + (this.nT - this.srcT);
        const added = new Float32Array(n * 9);
        const addedStates = new Uint8Array(n);
        let at = 0;
        const take = (t: number): void => { this.corners(t, added, at * 9); addedStates[at] = this.state[t]; at++; };
        changed.forEach(take);
        for (let t = this.srcT; t < this.nT; t++) take(t);
        return { triangles: this.srcT, states, added, addedStates };
    }

    /** Every edge has the triangle across it pointing back, over the same two vertices (a check for the tests). */
    sound(): string | null {
        for (let t = 0; t < this.nT; t++) {
            for (let k = 0; k < 3; k++) {
                const u = this.adj[t * 3 + k];
                if (u < 0) continue;
                const a = this.tri[t * 3 + k]; const b = this.tri[t * 3 + (k + 1) % 3];
                let back = false;
                for (let j = 0; j < 3; j++) {
                    const p = this.tri[u * 3 + j]; const q = this.tri[u * 3 + (j + 1) % 3];
                    if (this.adj[u * 3 + j] === t && ((p === b && q === a) || (p === a && q === b))) back = true;
                }
                if (!back) return `triangle ${t} edge ${k}: neighbour ${u} does not point back`;
            }
        }
        return null;
    }
}

/** The height bands as the shader and the server take them: sorted, inside the model, at most `max`. */
export function cleanBands(bands: { z: number; color: number }[], height: number, colors: number, max = 8): { z: number; color: number }[] {
    return bands.filter((b) => Number.isFinite(b.z) && b.z > 0 && b.z < height && Number.isInteger(b.color) && b.color >= 0 && b.color < colors)
        .map((b) => ({ z: Math.round(b.z * 100) / 100, color: b.color })).sort((a, b) => a.z - b.z).slice(0, max);
}

/**
 * The states the model ends up with: the indices of the palette that some triangle has, the bands counted in. A colour
 * of the palette that nothing is painted with does not count against the machine's limit.
 */
export function usedStates(areas: Float64Array, bands: { z: number; color: number }[]): number[] {
    const used = new Set<number>();
    for (let s = 0; s < 255; s++) if (areas[s] > 0) used.add(s);
    // a band recolours only what is not painted
    if (areas[0] > 0) bands.forEach((b) => used.add(b.color));
    return [...used].sort((a, b) => a - b);
}
