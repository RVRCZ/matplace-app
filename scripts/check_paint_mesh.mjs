// The mesh of the colouring tool (resources/js/calc/paint_mesh.ts, docs/AS.md) on small bodies whose answers are known:
// welding and neighbours, the fill by angle, the brush that refines without T-junctions, undo and redo, the record.
//   node scripts/check_paint_mesh.mjs            the checks (exit 1 when one fails; part of `npm run build`)
//   node scripts/check_paint_mesh.mjs --bench    the measurements of docs/AS.md §6 (50 k, 200 k, 1 M triangles)
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const { build } = createRequire(join(root, 'package.json'))('esbuild');
const out = await build({ entryPoints: [join(root, 'resources/js/calc/paint_mesh.ts')], bundle: true, format: 'esm', write: false });
const { PaintMesh, cleanBands, usedStates, REPLACED } = await import('data:text/javascript;base64,' + Buffer.from(out.outputFiles[0].text).toString('base64'));

let bad = 0;
const ok = (cond, what) => { if (!cond) { bad++; console.error('WRONG', what); } };
const near = (a, b, tol, what) => ok(Math.abs(a - b) <= tol, `${what}: ${a} is not ${b} ± ${tol}`);

/** A box of w × d × h whose faces are grids of n × n cells, two triangles each, wound outwards. */
function box(w, d, h, n) {
    const tris = [];
    const face = (o, u, v) => {
        for (let i = 0; i < n; i++) for (let j = 0; j < n; j++) {
            const p = (a, b) => [o[0] + u[0] * a / n + v[0] * b / n, o[1] + u[1] * a / n + v[1] * b / n, o[2] + u[2] * a / n + v[2] * b / n];
            const a = p(i, j), b = p(i + 1, j), c = p(i + 1, j + 1), e = p(i, j + 1);
            tris.push(...a, ...b, ...c, ...a, ...c, ...e);
        }
    };
    face([0, 0, h], [w, 0, 0], [0, d, 0]);     // top
    face([0, 0, 0], [0, d, 0], [w, 0, 0]);     // bottom
    face([0, 0, 0], [w, 0, 0], [0, 0, h]);     // front
    face([0, d, 0], [0, 0, h], [w, 0, 0]);     // back
    face([0, 0, 0], [0, 0, h], [0, d, 0]);     // left
    face([w, 0, 0], [0, d, 0], [0, 0, h]);     // right
    return new Float32Array(tris);
}

/** A ball of about `triangles` triangles (longitude and latitude). */
function ball(triangles, r = 30) {
    const n = Math.max(8, Math.round(Math.sqrt(triangles)));
    const m = Math.round(n / 2);
    const at = (i, j) => { const u = (i % n) / n * 2 * Math.PI, v = j / m * Math.PI; return [r * Math.sin(v) * Math.cos(u), r * Math.sin(v) * Math.sin(u), r * Math.cos(v)]; };
    const tris = [];
    for (let j = 0; j < m; j++) for (let i = 0; i < n; i++) {
        const a = at(i, j), b = at(i + 1, j), c = at(i + 1, j + 1), d = at(i, j + 1);
        if (j > 0) tris.push(...a, ...d, ...b);
        if (j < m - 1) tris.push(...b, ...d, ...c);
    }
    return new Float32Array(tris);
}

const totalArea = (mesh) => { let a = 0; for (let t = 0; t < mesh.nT; t++) a += mesh.area(t); return a; };

// ── a cube of 12 triangles: welded to 8 corners, every edge has its neighbour, a fill stops at the edges ──
{
    const mesh = PaintMesh.fromSoup(box(10, 10, 10, 1));
    ok(mesh.nT === 12 && mesh.nV === 8, `cube: ${mesh.nT} triangles, ${mesh.nV} vertices`);
    ok(mesh.sound() === null, `cube neighbours: ${mesh.sound()}`);
    ok([...mesh.adj.slice(0, 36)].every((u) => u >= 0), 'cube: a closed body has a neighbour across every edge');
    mesh.begin();
    ok(mesh.flood(0, 30, 1) === 2, 'a fill at 30° takes the two triangles of one face');
    const step = mesh.end();
    ok(step && step.tris.length === 2, 'the step holds two triangles');
    near(mesh.areas()[1], 100, 1e-3, 'the face is 100 mm²');
    mesh.begin();
    ok(mesh.flood(0, 180, 2) === 2, 'a fill of the whole part goes on only over the colour it started on');
    ok(mesh.flood(2, 180, 3) === 10, 'the rest of the body is the other ten triangles');
    const step2 = mesh.end();
    ok(mesh.flood(2, 180, 3) === 0, 'a fill in the colour that is there changes nothing');
    mesh.undo(step2);
    ok(mesh.state[0] === 1 && mesh.state[2] === 0, 'undo puts the states back');
    mesh.redo(step2);
    ok(mesh.state[0] === 2 && mesh.state[2] === 3, 'redo paints again');
    const rec = mesh.record();
    ok(rec.triangles === 12 && rec.states.length === 12 && rec.added.length === 0 && rec.addedStates.length === 0, 'a record without a brush has no added triangles');
    ok(rec.states[0] === 2 && rec.states[2] === 3, 'the record carries the states');
    const [lo, hi] = mesh.heightRange();
    ok(lo === 0 && hi === 10, 'the height range');
}

// ── a finer box: the fill by angle stops at the box's edges and at another colour ──
{
    const mesh = PaintMesh.fromSoup(box(20, 20, 10, 10));
    ok(mesh.nT === 1200 && mesh.sound() === null, 'box 10 × 10 cells');
    mesh.begin();
    ok(mesh.flood(0, 30, 1) === 200, 'the top face: 200 triangles');
    mesh.end();
    // a line of another colour across the bottom face (one row of cells): a fill beside it stops there
    mesh.setRanges([{ from: 200 + 100, to: 200 + 120, state: 2 }]);
    mesh.begin();
    ok(mesh.flood(200, 30, 3) === 100, 'a fill stops at a line of another colour: the five rows before it');
    ok(mesh.flood(200 + 120, 30, 4) === 80, 'and the four rows behind it are a patch of their own');
    mesh.end();
}

// ── the brush: a disc of the right area, no T-junction, the same surface, bounded growth ──
{
    const mesh = PaintMesh.fromSoup(box(40, 40, 10, 2));
    const before = totalArea(mesh);
    mesh.begin();
    // the middle of the top face, a ball of 5 mm, edges down to 0.4 mm
    let seed = -1;
    for (let t = 0; t < mesh.nT && seed < 0; t++) if (mesh.rayHit(t, [20.3, 19.6, 50], [0, 0, -1]) > 0) seed = t;
    ok(seed >= 0, 'a ray from above hits the top');
    const hit = mesh.hitNear(seed, [20.3, 19.6, 50], [0, 0, -1]);
    near(hit[1], 40, 1e-4, 'the top is 40 mm below the eye');
    ok(mesh.brush(seed, [20.3, 19.6, 10], 5, 1, 0.4, 500000) === true, 'the brush had room to refine');
    const step = mesh.end();
    const painted = mesh.areas()[1];
    near(painted, Math.PI * 25, Math.PI * 25 * 0.01, 'the dab is a disc of 5 mm (within 1 %: its rim is cut where the ball crosses the edges)');
    ok(mesh.sound() === null, `after the brush: ${mesh.sound()}`);
    near(totalArea(mesh), before, 1e-2, 'the refined mesh covers the same area');
    ok(mesh.added() > 200 && mesh.added() < 6000, `the brush added ${mesh.added()} triangles`);
    // every painted triangle lies within the ball (its middle), every triangle well inside it is painted
    let outside = 0, missed = 0;
    const c = new Float32Array(9);
    for (let t = 0; t < mesh.nT; t++) {
        mesh.corners(t, c);
        const mx = (c[0] + c[3] + c[6]) / 3 - 20.3, my = (c[1] + c[4] + c[7]) / 3 - 19.6, mz = (c[2] + c[5] + c[8]) / 3 - 10;
        const d = Math.hypot(mx, my, mz);
        if (mesh.state[t] === 1 && d > 5.0001) outside++;
        if (mesh.state[t] !== 1 && d < 4.9 && Math.abs(mz) < 1e-6) missed++;
    }
    ok(outside === 0 && missed === 0, `the dab's rim: ${outside} painted outside, ${missed} missed inside`);
    // the record: the refined source triangles are marked, the added ones carry the whole refined area
    const rec = mesh.record();
    const replaced = rec.states.filter((s) => s === REPLACED).length;
    ok(replaced >= 2 && rec.added.length === rec.addedStates.length * 9 && rec.addedStates.length === replaced + mesh.added(), 'the record names what was refined');
    let kept = 0;
    for (let t = 0; t < mesh.srcT; t++) if (rec.states[t] !== REPLACED) kept += mesh.area(t);
    let add = 0;
    for (let i = 0; i < rec.addedStates.length; i++) {
        const a = rec.added.subarray(i * 9, i * 9 + 9);
        const ux = a[3] - a[0], uy = a[4] - a[1], uz = a[5] - a[2], vx = a[6] - a[0], vy = a[7] - a[1], vz = a[8] - a[2];
        add += 0.5 * Math.hypot(uy * vz - uz * vy, uz * vx - ux * vz, ux * vy - uy * vx);
    }
    near(kept + add, before, 1e-2, 'the kept and the added triangles are the whole surface');
    // undo: the paint goes, the refinement stays; redo paints the same triangles
    mesh.undo(step);
    ok(mesh.areas()[1] === 0, 'undo takes the dab away, on the refined triangles too');
    mesh.redo(step);
    near(mesh.areas()[1], painted, 1e-9, 'redo brings it back');
    // a second stroke over the first in another colour and its undo: the first one is whole again
    mesh.begin();
    mesh.brush(mesh.hitNear(seed, [22, 19.6, 50], [0, 0, -1])[0], [22, 19.6, 10], 3, 2, 0.4, 500000);
    const step2 = mesh.end();
    ok(mesh.areas()[2] > 20 && mesh.sound() === null, 'a second stroke over the first');
    mesh.undo(step2);
    near(mesh.areas()[1], painted, 1e-6, 'undo of the second stroke leaves the first as it was');
    ok(mesh.areas()[2] === 0, 'and nothing of the second');
    // at its limit of triangles the brush still paints, only coarser
    const coarse = PaintMesh.fromSoup(box(40, 40, 10, 2));
    coarse.begin();
    ok(coarse.brush(0, [20, 20, 10], 5, 1, 0.4, coarse.nT + 10) === false, 'the limit is said');
    coarse.end();
    ok(coarse.added() === 0 && coarse.sound() === null && coarse.areas()[1] === 0, 'and kept: nothing is split, a triangle goes by its middle');
}

// ── a stroke of dabs that know their way: the same band as dabs that do not, with far fewer triangles ──
{
    const stroke = (withDir) => {
        const mesh = PaintMesh.fromSoup(box(60, 30, 5, 3));
        let seed = -1;
        for (let t = 0; t < mesh.nT && seed < 0; t++) if (mesh.rayHit(t, [10, 15, 50], [0, 0, -1]) > 0) seed = t;
        mesh.begin();
        mesh.strokeStart(3, 1);
        for (let i = 0; i <= 40; i++) {
            const x = 10 + i;
            const hit = mesh.hitNear(seed, [x, 15, 50], [0, 0, -1], mesh.nT);
            seed = hit[0];
            mesh.strokeDab(seed, [x, 15, 5], 0.4, 2000000, withDir && i > 0 ? [1, 0, 0] : null);
        }
        mesh.strokeEnd();
        mesh.end();
        ok(mesh.sound() === null, `a stroke: ${mesh.sound()}`);
        return mesh;
    };
    const plain = stroke(false); const guided = stroke(true);
    const band = 40 * 6 + Math.PI * 9;      // a band of 40 × 6 mm with round ends
    near(plain.areas()[1], band, band * 0.01, 'a stroke of dabs is a band with round ends');
    near(guided.areas()[1], band, band * 0.005, 'dabs that know their way paint the band with straight sides');
    ok(guided.added() < plain.added() * 0.6, `and refine less: ${guided.added()} against ${plain.added()} triangles`);
}

// ── a wavy stroke over a curved surface, drawn the way the page draws it: the rims of its dabs graze one another, and
//    the brush once cut one spot down to nothing until it ran out of triangles (400 000 slivers from one stroke) ──
{
    const mesh = PaintMesh.fromSoup(ball(50000));
    const top = mesh.hitNear(0, [-20, 0, 100], [0, 0, -1], mesh.nT);
    ok(top !== null, 'the start of the wavy stroke');
    mesh.begin();
    mesh.strokeStart(2.5, 1);
    let seed = top[0]; let last = null; let was = null;
    for (let i = 0; i <= 90; i++) {
        const x = -20 + i * 0.45; const y = 4 * Math.sin(x / 3);
        const hit = mesh.hitNear(seed, [x, y, 100], [0, 0, -1], 20000);
        if (!hit) continue;
        seed = hit[0];
        const point = [x, y, 100 - hit[1]];
        let dir = null;
        if (last) { const d = [point[0] - last[0], point[1] - last[1], point[2] - last[2]]; const len = Math.hypot(...d); dir = d.map((v) => v / len); if (was && was[0] * dir[0] + was[1] * dir[1] + was[2] * dir[2] < 0.7) dir = null; }
        mesh.strokeDab(seed, point, 1.0, mesh.srcT + 400000, dir);
        was = dir; last = point;
    }
    mesh.strokeEnd();
    mesh.end();
    ok(mesh.sound() === null, `the wavy stroke: ${mesh.sound()}`);
    ok(mesh.added() > 500 && mesh.added() < 12000, `the wavy stroke added ${mesh.added()} triangles`);
    let slivers = 0;
    for (let t = mesh.srcT; t < mesh.nT; t++) if (mesh.area(t) < 1e-7) slivers++;
    ok(slivers === 0, `${slivers} of the added triangles have no area`);
    // about a band of 5 mm along the wave (its length 44 mm and a little), nothing far from it
    const painted = mesh.areas()[1];
    ok(painted > 200 && painted < 320, `the wavy stroke painted ${Math.round(painted)} mm²`);
}

// ── the eraser is the brush with state 0; the fill over a brushed line ──
{
    const mesh = PaintMesh.fromSoup(ball(20000));
    ok(mesh.sound() === null, `ball: ${mesh.sound()}`);
    mesh.begin();
    ok(mesh.flood(100, 180, 1) === mesh.nT, 'the whole ball is one body');
    const top = mesh.hitNear(0, [0.1, 0.2, 100], [0, 0, -1], 100000);
    ok(top !== null, 'the top of the ball is found from a far guess');
    mesh.brush(top[0], [0.1, 0.2, 30], 4, 0, 0.4, 500000);
    mesh.end();
    near(mesh.areas()[0], Math.PI * 16, Math.PI * 16 * 0.03, 'the eraser leaves a disc of the model\'s own colour');
}

// ── what the colours cost: filament changes by layer ──
{
    const mesh = PaintMesh.fromSoup(box(10, 10, 10, 1));
    ok(mesh.colourChanges([]) === 0, 'one colour: no change');
    ok(mesh.colourChanges([{ z: 5, color: 1 }]) === 1, 'two colours one above the other: one change');
    ok(mesh.colourChanges([{ z: 3, color: 1 }, { z: 7, color: 2 }]) === 2, 'three colours one above another: two changes');
    mesh.begin();
    mesh.flood(4, 30, 1);        // a side of the cube, bottom to top
    mesh.end();
    ok(mesh.colourChanges([]) === 50, `a side in another colour: a change in each of the 50 layers (${mesh.colourChanges([])})`);
    ok(mesh.colourChanges([{ z: 5, color: 2 }]) === 50, 'and a band beside it changes nothing about that: still two colours a layer');
}

// ── bands and the count of colours ──
{
    const bands = cleanBands([{ z: 30, color: 2 }, { z: 5, color: 1 }, { z: 0, color: 1 }, { z: 60, color: 1 }, { z: 7, color: 9 }], 50, 4);
    ok(JSON.stringify(bands) === JSON.stringify([{ z: 5, color: 1 }, { z: 30, color: 2 }]), `bands sorted and kept inside the model: ${JSON.stringify(bands)}`);
    const areas = new Float64Array(256);
    areas[0] = 10; areas[3] = 2;
    ok(JSON.stringify(usedStates(areas, bands)) === '[0,1,2,3]', 'colours in use: painted ones and the bands');
    areas[0] = 0;
    ok(JSON.stringify(usedStates(areas, bands)) === '[3]', 'bands colour only what is not painted');
}

if (process.argv.includes('--bench')) {
    const ms = (f) => { const t = performance.now(); const r = f(); return [Math.round(performance.now() - t), r]; };
    for (const n of [50000, 200000, 1000000]) {
        const soup = ball(n);
        const [weld, mesh] = ms(() => PaintMesh.fromSoup(soup));
        mesh.begin();
        const [flood] = ms(() => mesh.flood(0, 180, 1));
        const [areas] = ms(() => mesh.areas());
        const top = mesh.hitNear(0, [0.1, 0.2, 100], [0, 0, -1], mesh.nT);
        // a stroke of 60 dabs of 2 mm, 0.9 mm apart, across the top
        const at = top[0];
        // (the way the page draws it: every dab but the first and the last knows which way the stroke goes)
        mesh.strokeStart(2, 2);
        const [stroke] = ms(() => { let s = at; for (let i = 0; i < 60; i++) { const x = -27 + i * 0.9; const z = Math.sqrt(900 - x * x); const h = mesh.hitNear(s, [x, 0.2, 100], [0, 0, -1], 5000) ?? mesh.hitNear(at, [x, 0.2, 100], [0, 0, -1], mesh.nT); if (h) { s = h[0]; const x1 = x + 0.9; const z1 = Math.sqrt(900 - x1 * x1); const len = Math.hypot(0.9, z1 - z); mesh.strokeDab(s, [x, 0.2, z], 0.4, mesh.srcT + 400000, i === 0 ? null : [0.9 / len, 0, (z1 - z) / len]); } } mesh.strokeEnd(); });
        mesh.end();
        const [record] = ms(() => mesh.record());
        console.log(`${mesh.srcT} triangles: weld + neighbours ${weld} ms, fill of all ${flood} ms, areas ${areas} ms, a stroke of 60 dabs ${stroke} ms (+${mesh.added()} triangles), record ${record} ms`);
    }
}

if (bad) { console.error(`${bad} wrong`); process.exit(1); }
console.log('paint_mesh.ts: welding, fill, brush, undo and record agree with the known bodies');
