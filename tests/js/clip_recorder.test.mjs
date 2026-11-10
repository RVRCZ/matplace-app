import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const source = new URL('../../resources/js/site/clip_recorder.ts', import.meta.url);
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'matplace-clip-recorder-'));
const compiler = path.resolve('node_modules/typescript/bin/tsc');
execFileSync(process.execPath, [compiler, fileURLToPath(source), '--ignoreConfig', '--target', 'ES2022', '--module', 'ESNext', '--outDir', temporary, '--skipLibCheck']);
const js = fs.readFileSync(path.join(temporary, 'clip_recorder.js'), 'utf8');
fs.rmSync(temporary, { recursive: true, force: true });
const module = await import(`data:text/javascript;base64,${Buffer.from(js).toString('base64')}`);
const { CLIP_LIMITS, recordClip, availableDevices, rewindPreview } = module;

function fakeBrowser() {
    const constraints = [];
    const timeouts = [];
    const tracks = [{ stopped: 0, stop() { this.stopped++; } }, { stopped: 0, stop() { this.stopped++; } }];
    const oldTimeout = globalThis.setTimeout;
    const oldRecorder = globalThis.MediaRecorder;
    globalThis.setTimeout = (callback, milliseconds) => { timeouts.push(milliseconds); queueMicrotask(callback); return 1; };
    globalThis.MediaRecorder = class {
        static isTypeSupported() { return true; }
        constructor(stream, options) { this.stream = stream; this.mimeType = options?.mimeType ?? 'video/webm'; this.state = 'inactive'; }
        start() { this.state = 'recording'; this.ondataavailable({ data: new Blob(['movie']) }); }
        stop() { this.state = 'inactive'; this.onstop(); }
    };
    const capture = {
        async getUserMedia(value) { constraints.push(value); return { getTracks: () => tracks }; },
        async enumerateDevices() { return [{ kind: 'videoinput', deviceId: 'rear' }, { kind: 'audioinput', deviceId: 'mic' }, { kind: 'audiooutput', deviceId: 'speaker' }]; },
    };
    return { capture, constraints, timeouts, tracks, restore() { globalThis.setTimeout = oldTimeout; globalThis.MediaRecorder = oldRecorder; } };
}

test('all five clip types record with microphone, portrait video and automatic stop', async () => {
    for (const [kind, seconds] of Object.entries(CLIP_LIMITS)) {
        const browser = fakeBrowser();
        try {
            const result = await recordClip(kind, 'rear', 'mic', browser.capture);
            assert.equal(result.blob.size, 5);
            assert.equal(result.mime, 'video/webm;codecs=vp9,opus');
            assert.deepEqual(browser.timeouts, [seconds * 1000]);
            assert.equal(browser.constraints[0].video.width.ideal, 1080);
            assert.equal(browser.constraints[0].video.height.ideal, 1920);
            assert.equal(browser.constraints[0].video.facingMode.ideal, 'environment');
            assert.deepEqual(browser.constraints[0].video.deviceId, { exact: 'rear' });
            assert.deepEqual(browser.constraints[0].audio, { deviceId: { exact: 'mic' } });
            assert.deepEqual(browser.tracks.map((track) => track.stopped), [1, 1]);
        } finally {
            browser.restore();
        }
    }
});

test('available cameras and microphones are enumerated separately and preview rewinds', async () => {
    const browser = fakeBrowser();
    assert.deepEqual((await availableDevices(browser.capture, 'videoinput')).map((device) => device.deviceId), ['rear']);
    assert.deepEqual((await availableDevices(browser.capture, 'audioinput')).map((device) => device.deviceId), ['mic']);
    let played = 0;
    const preview = { currentTime: 2, async play() { played++; } };
    rewindPreview(preview);
    assert.equal(preview.currentTime, 0);
    assert.equal(played, 1);
    browser.restore();
});
