<?php

namespace App\Jobs;

use App\Domain\Tools\ModelEditor;
use App\Engines\Repair\PythonTool;
use App\Models\ModelFile;
use App\Support\Stopwatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs one editing operation (split, scale…) of engines/python/edit_tool.py on a ready model and turns the answer into
 * the new file's STL, its pieces and its report; then the usual processing (ProcessModelFile) makes it ready.
 * The phases are timed into `timings` (edit_s: the tool; the rest as ProcessModelFile counts them).
 */
class EditModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $modelFileId)
    {
        $this->onQueue(config('queue.interactive'));
    }

    public function handle(PythonTool $python): void
    {
        $file = ModelFile::find($this->modelFileId);
        if (! $file || $file->status !== ModelFile::STATUS_UPLOADED || $file->origin !== 'tool') {
            return;
        }
        $op = $file->kind();
        $params = (array) $file->tool_params;
        $src = ModelFile::where('uuid', (string) ($params['source'] ?? ''))->first();
        $clock = (new Stopwatch)->note('started', round(microtime(true), 1))->since('queue', $file->created_at?->getTimestamp());
        $dst = Storage::disk(ModelFile::DISK)->path($file->storage_path);
        try {
            if (! $src || ! $src->isReady() || ! in_array($op, ModelEditor::KINDS, true)) {
                throw new \RuntimeException('source_gone');
            }
            $file->status = ModelFile::STATUS_PROCESSING;
            $file->save();
            File::ensureDirectoryExists(dirname($dst));
            $json = dirname($dst).'/edit.json';
            File::put($json, (string) json_encode(ModelEditor::forTool($op, array_diff_key($params, ['source' => 1]))));
            $r = $clock->measure('edit', fn () => $python->runScript('edit_tool.py', [$op, $src->absoluteStlPath(), $dst, '@'.$json, dirname($dst).'/parts'], 840));
            @unlink($json);
            @unlink($dst.'.stage');
            if (empty($r['ok']) || ! is_file($dst)) {
                throw new \RuntimeException((string) ($r['code'] ?? $r['error'] ?? 'edit_failed'));
            }
            $notes = (array) ($r['notes'] ?? []);
            $params['parts'] = array_values((array) ($notes['parts'] ?? []));
            $params['parts_bbox'] = array_map(fn ($p) => [round($p['bbox'][3] - $p['bbox'][0], 2), round($p['bbox'][4] - $p['bbox'][1], 2), round($p['bbox'][5] - $p['bbox'][2], 2)], array_column((array) ($r['parts'] ?? []), null, 'name'));
            $params['pieces'] = array_values((array) ($r['parts'] ?? []));
            $params['each'] = (array) ($notes['each'] ?? []);
            $params['report'] = array_diff_key($notes, ['parts' => 1, 'each' => 1]) + ['triangles' => (int) ($r['triangles'] ?? 0)];
            $file->tool_params = $params;
            $file->size_bytes = filesize($dst);
            $file->sha256 = hash_file('sha256', $dst);
            $file->status = ModelFile::STATUS_UPLOADED;
            $file->save();
            // the ordinary processing: the normalised STL, the geometry, the mesh report, "ready"
            ProcessModelFile::dispatchSync($file->id);
            $file->refresh();
            $file->timings = $clock->merge((array) ($file->timings ?? []))->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
        } catch (\Throwable $e) {
            Log::warning('EditModel failed', ['id' => $file->id, 'op' => $op, 'error' => $e->getMessage()]);
            @unlink($dst.'.stage');
            $file->status = ModelFile::STATUS_FAILED;
            $file->error = mb_substr($e->getMessage(), 0, 1000);
            $file->timings = $clock->note('failed', true)->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
        }
    }
}
