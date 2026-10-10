<?php

namespace App\Jobs;

use App\Domain\Tools\MapBuilder;
use App\Domain\Tools\MapDataUnavailable;
use App\Models\ModelFile;
use App\Support\Stopwatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Makes the map of a design (/tools/map): fetches the data of the place (or takes them from the cache), builds the
 * STL with engines/python/map_tool.py, keeps what the print and the viewer need in tool_params (the colours by
 * height, the notes), and hands the file to the usual processing (ProcessModelFile) that makes it ready. The phase
 * goes to <original.stl>.stage for the page: osm → terrain → reading → buildings → roads → writing → done.
 */
class BuildMap implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $modelFileId)
    {
        $this->onQueue(config('queue.interactive'));
    }

    public function handle(MapBuilder $maps): void
    {
        $file = ModelFile::find($this->modelFileId);
        if (! $file || $file->status !== ModelFile::STATUS_UPLOADED || $file->kind() !== MapBuilder::KIND) {
            return;
        }
        $params = (array) $file->tool_params;
        $clock = (new Stopwatch)->note('started', round(microtime(true), 1))->since('queue', $file->created_at?->getTimestamp());
        $dst = Storage::disk(ModelFile::DISK)->path($file->storage_path);
        $stage = function (string $name) use ($dst): void {
            File::ensureDirectoryExists(dirname($dst));
            @file_put_contents($dst.'.stage', $name);
        };
        try {
            $file->status = ModelFile::STATUS_PROCESSING;
            $file->save();
            $sources = $clock->measure('fetch', fn () => $maps->sources($params, $stage));
            $built = $clock->measure('build', fn () => $maps->build($sources, $dst, 540));
            @unlink($dst.'.stage');
            $notes = $built['notes'];
            $params['notes'] = array_diff_key($notes, ['regions' => 1, 'color_changes' => 1, 'colors' => 1]);
            $params['regions'] = array_values((array) ($notes['regions'] ?? []));
            // the colours by height: what the farm ticks its spools by and the slicer project stops the printer for
            $params['color_changes'] = array_values((array) ($notes['color_changes'] ?? []));
            $params['multi_material'] = false;
            $file->tool_params = $params;
            $file->size_bytes = filesize($dst);
            $file->sha256 = hash_file('sha256', $dst);
            $file->status = ModelFile::STATUS_UPLOADED;
            $file->save();
            ProcessModelFile::dispatchSync($file->id);
            $file->refresh();
            $file->timings = $clock->merge((array) ($file->timings ?? []))->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
        } catch (\Throwable $e) {
            Log::warning('BuildMap failed', ['id' => $file->id, 'error' => $e->getMessage()]);
            @unlink($dst.'.stage');
            $file->status = ModelFile::STATUS_FAILED;
            // the code the page translates: a service that did not answer, or what the builder said
            $file->error = $e instanceof MapDataUnavailable ? $e->reason : mb_substr($e->getMessage(), 0, 200);
            // the detail (which server said what) stays with the file: the log line is gone sooner than the question why
            $file->timings = $clock->note('failed', true)->note('failed_why', mb_substr($e->getMessage(), 0, 300))->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
        }
    }
}
