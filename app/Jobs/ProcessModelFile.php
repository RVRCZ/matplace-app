<?php

namespace App\Jobs;

use App\Domain\Farm\OrderFlow;
use App\Domain\Files\PaintedColors;
use App\Engines\Contracts\MeshRepair;
use App\Engines\Converter\ConverterChain;
use App\Engines\Mesh\StlFile;
use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Support\Stopwatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Upload → normalised binary STL → geometry + mesh report → ready.
 * Any input format goes through the converter chain first (3MF, OBJ, STEP…), so downstream
 * (slicer, viewer, download) always works with one STL.
 */
class ProcessModelFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public readonly int $modelFileId)
    {
        // a customer waits for it; a designer's card file is dispatched onto the ordinary queue instead (CardFiles)
        $this->onQueue(config('queue.interactive'));
    }

    public function handle(ConverterChain $converters, MeshRepair $repair): void
    {
        $file = ModelFile::find($this->modelFileId);
        if (! $file || $file->status === ModelFile::STATUS_READY) {
            return;
        }
        $file->status = ModelFile::STATUS_PROCESSING;
        $file->save();
        // the file waited in the queue since it was stored (uploaded, generated, repaired…)
        $clock = (new Stopwatch)->note('started', round(microtime(true), 1))->since('queue', $file->created_at?->getTimestamp());

        try {
            $disk = Storage::disk(ModelFile::DISK);
            $in = $file->absolutePath();
            $stlRel = $file->dir().'/model.stl';
            $stlAbs = $disk->path($stlRel);
            File::ensureDirectoryExists(dirname($stlAbs));

            $clock->measure('convert', function () use ($file, $converters, $in, $stlAbs, $clock) {
                if ($file->ext === 'stl') {
                    // a sound binary STL is what everything downstream wants already: copied as it is. Rewriting it
                    // triangle by triangle in PHP took most of the 23 s a calculation of 2.7 M triangles waited for its
                    // file (24 Oct 2026, docs/AL.md §11); an ASCII file, or a binary one whose header says "solid"
                    // (slicers take that for ASCII), is still rewritten into a binary one with a plain header
                    if (StlFile::binaryCount($in) > 0 && ! str_starts_with((string) file_get_contents($in, false, null, 0, 5), 'solid')) {
                        $clock->note('convert', 'copy');
                        if (! @copy($in, $stlAbs)) {
                            StlFile::scale($in, $stlAbs, 1.0);
                        }
                    } else {
                        StlFile::scale($in, $stlAbs, 1.0);
                    }
                } else {
                    $converters->convert($in, 'stl', $stlAbs);
                }
            });

            // a 3MF painted in colours (Bambu Studio, OrcaSlicer, Anycubic Slicer Next, PrusaSlicer): its colours read, the
            // STL ordered by them for the preview (docs/AL.md §14); a file without colours, or one that cannot be read, stays as it was
            if ($file->ext === '3mf') {
                $file->colors = $clock->measure('colors', fn () => app(PaintedColors::class)->analyse($file, $stlAbs));
            }
            $report = $clock->measure('analyse', fn () => $repair->check($stlAbs));
            if ($report->triangles > (int) config('engines.mesh.max_triangles')) {
                // beyond what thinning before the slice (MeshDecimator) can make printable in reasonable time: the customer
                // is told to thin the model at home (the error code is what the pages read)
                throw new \RuntimeException('too_many_triangles');
            }
            $file->stl_path = $stlRel;
            $file->bbox = $report->bbox->toArray();
            $file->volume_mm3 = $report->volumeMm3;
            $file->area_mm2 = $report->areaMm2;
            $file->triangles = $report->triangles;
            $file->mesh_report = $report->toArray();
            $file->status = ModelFile::STATUS_READY;
            $file->error = null;
            $file->timings = $clock->note('triangles', $report->triangles)->note('mb', round(filesize($stlAbs) / 1048576, 1))->note('ext', $file->ext)
                ->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
        } catch (\Throwable $e) {
            Log::warning('ProcessModelFile failed', ['id' => $file->id, 'error' => $e->getMessage()]);
            $file->status = ModelFile::STATUS_FAILED;
            $file->error = mb_substr($e->getMessage(), 0, 1000);
            $file->timings = $clock->note('failed', true)->note('finished', round(microtime(true), 1))->toArray();
            $file->save();
            // calculations waiting for this file cannot proceed
            Calculation::where('model_file_id', $file->id)
                ->whereIn('status', [Calculation::STATUS_QUEUED, Calculation::STATUS_ROUGH])
                ->update(['status' => Calculation::STATUS_FAILED, 'error' => 'file_processing_failed']);
        }
        $this->startWaiting($file);
    }

    /**
     * The precise calculations and farm orders made while the file was still being processed start now, instead of
     * asking every few seconds whether it is ready. Their own jobs also start them when the file was ready first;
     * a second copy finds the work claimed or done and does nothing.
     */
    private function startWaiting(ModelFile $file): void
    {
        if ($file->status === ModelFile::STATUS_READY) {
            Calculation::where('model_file_id', $file->id)->where('status', Calculation::STATUS_QUEUED)->pluck('id')
                ->each(fn (int $id) => SliceCalculation::dispatch($id));
        }
        // a failed file fails its orders there, with the farm's own reason
        FarmOrder::where('model_file_id', $file->id)->where('status', FarmOrder::STATUS_UPLOADED)->pluck('id')
            ->each(fn (int $id) => PrepareFarmOrder::dispatch($id));
        if ($file->status === ModelFile::STATUS_FAILED) {
            // a big model waiting for its customer's choices has no job to fail it: failed here
            $flow = app(OrderFlow::class);
            FarmOrder::where('model_file_id', $file->id)->where('status', FarmOrder::STATUS_AWAITING_CHOICES)->get()
                ->each(fn (FarmOrder $o) => $flow->fail($o, 'file_unreadable', 'system', detail: $file->error));
        }
    }
}
