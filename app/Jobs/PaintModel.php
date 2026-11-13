<?php

namespace App\Jobs;

use App\Domain\Tools\ModelPainter;
use App\Models\ModelFile;
use App\Support\Stopwatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * A model coloured in the browser (App\Domain\Tools\ModelPainter, docs/AS.md): the painted 3MF written from the record,
 * then the ordinary processing of an uploaded painted 3MF (the STL, the colours read back, "ready").
 */
class PaintModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $modelFileId)
    {
        $this->onQueue(config('queue.interactive'));
    }

    public function handle(ModelPainter $painter): void
    {
        $file = ModelFile::find($this->modelFileId);
        if (! $file || $file->status !== ModelFile::STATUS_UPLOADED || $file->origin !== ModelPainter::KIND) {
            return;
        }
        $clock = (new Stopwatch)->note('started', round(microtime(true), 1))->since('queue', $file->created_at?->getTimestamp());
        try {
            $src = ModelFile::where('uuid', (string) $file->origin_ref)->first();
            if (! $src || ! $src->isReady()) {
                throw new \RuntimeException('source_gone');
            }
            $file->status = ModelFile::STATUS_PROCESSING;
            $file->save();
            $r = $clock->measure('paint', fn () => $painter->build($file, $src));
            $params = (array) $file->tool_params;
            $params['paint']['report'] = [
                'triangles' => (int) ($r['triangles'] ?? 0), 'refined' => (int) ($r['refined'] ?? 0), 'cut' => (int) ($r['cut'] ?? 0),
                'colors' => array_values((array) ($r['colors'] ?? [])), 'specks' => (array) ($r['specks'] ?? []),
            ];
            $dst = $file->absolutePath();
            $file->tool_params = $params;
            $file->size_bytes = (int) filesize($dst);
            $file->sha256 = (string) hash_file('sha256', $dst);
            $file->status = ModelFile::STATUS_UPLOADED;
            $file->save();
            ProcessModelFile::dispatchSync($file->id);
            $file->refresh();
            $file->timings = $clock->merge((array) ($file->timings ?? []))->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
        } catch (\Throwable $e) {
            Log::warning('PaintModel failed', ['id' => $file->id, 'error' => $e->getMessage()]);
            $file->status = ModelFile::STATUS_FAILED;
            $file->error = mb_substr($e->getMessage(), 0, 1000);
            $file->timings = $clock->note('failed', true)->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
        }
    }
}
