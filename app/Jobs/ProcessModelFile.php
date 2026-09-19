<?php

namespace App\Jobs;

use App\Engines\Contracts\MeshRepair;
use App\Engines\Converter\ConverterChain;
use App\Engines\Mesh\StlFile;
use App\Models\Calculation;
use App\Models\ModelFile;
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

    public function __construct(public readonly int $modelFileId) {}

    public function handle(ConverterChain $converters, MeshRepair $repair): void
    {
        $file = ModelFile::find($this->modelFileId);
        if (! $file || $file->status === ModelFile::STATUS_READY) {
            return;
        }
        $file->status = ModelFile::STATUS_PROCESSING;
        $file->save();

        try {
            $disk = Storage::disk(ModelFile::DISK);
            $in = $file->absolutePath();
            $stlRel = $file->dir().'/model.stl';
            $stlAbs = $disk->path($stlRel);
            File::ensureDirectoryExists(dirname($stlAbs));

            if ($file->ext === 'stl') {
                // normalise (ASCII → binary, consistent header) by rewriting through StlFile
                StlFile::scale($in, $stlAbs, 1.0);
            } else {
                $converters->convert($in, 'stl', $stlAbs);
            }

            $report = $repair->check($stlAbs);
            $file->stl_path = $stlRel;
            $file->bbox = $report->bbox->toArray();
            $file->volume_mm3 = $report->volumeMm3;
            $file->area_mm2 = $report->areaMm2;
            $file->triangles = $report->triangles;
            $file->mesh_report = $report->toArray();
            $file->status = ModelFile::STATUS_READY;
            $file->error = null;
            $file->save();
        } catch (\Throwable $e) {
            Log::warning('ProcessModelFile failed', ['id' => $file->id, 'error' => $e->getMessage()]);
            $file->status = ModelFile::STATUS_FAILED;
            $file->error = mb_substr($e->getMessage(), 0, 1000);
            $file->save();
            // calculations waiting for this file cannot proceed
            Calculation::where('model_file_id', $file->id)
                ->whereIn('status', [Calculation::STATUS_QUEUED, Calculation::STATUS_ROUGH])
                ->update(['status' => Calculation::STATUS_FAILED, 'error' => 'file_processing_failed']);
        }
    }
}
