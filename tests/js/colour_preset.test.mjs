import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';

const source = new URL('../../resources/js/calc/colour_preset.ts', import.meta.url);
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'matplace-colour-preset-'));
const compiler = path.resolve('node_modules/typescript/bin/tsc');
execFileSync(process.execPath, [compiler, fileURLToPath(source), '--ignoreConfig', '--target', 'ES2022', '--module', 'ESNext', '--outDir', temporary, '--skipLibCheck']);
fs.writeFileSync(path.join(temporary, 'package.json'), '{"type":"module"}');
const { nearestSetIndex, presetColours } = await import(pathToFileURL(path.join(temporary, 'colour_preset.js')).href);

const warm = [{ id: 1, hex: '#ffffff' }, { id: 2, hex: '#d4a017' }, { id: 3, hex: '#8b0000' }];
const cool = [{ id: 4, hex: '#ffffff' }, { id: 5, hex: '#1e3a8a' }, { id: 6, hex: '#0ea5e9' }];

test('the set nearest to the design is the first one', () => {
    assert.equal(nearestSetIndex([cool, warm], ['#ffffff', '#cc9900', '#990000']), 1);
    assert.equal(nearestSetIndex([warm, cool], ['#ffffff', '#1e40af', '#38bdf8']), 1);
});

test('layers get the nearest colour of the set and never repeat the one before', () => {
    const r = presetColours(warm, warm, '#fefefe', ['#d0a010', '#d9aa1a'], false);
    assert.equal(r.main, 1);
    assert.deepEqual(r.ids, [2, 3]);   // the second yellow would repeat gold, which is no change: the next nearest instead
});

test('parts may share a colour', () => {
    const r = presetColours(warm, warm, '#ffffff', ['#d0a010', '#d9aa1a', '#880000'], true);
    assert.deepEqual(r.ids, [2, 2, 3]);
});

test('the first colour is chosen among the openers only', () => {
    const r = presetColours([warm[1], warm[2]], warm, '#ffffff', [], false);
    assert.equal(r.main, 2);
});
