<?php

namespace App\Domain\Catalog;

use App\Domain\Calculation\CalculationService;
use App\Jobs\SliceCalculation;
use App\Models\Calculation;
use App\Models\CatalogModel;
use App\Models\ModelFile;
use Illuminate\Support\Facades\Storage;

/**
 * One inspiration model sliced from its own file (docs/AN.md §13): the calculator's defaults (PLA, standard, 15 %
 * infill, one piece), the slice run in this process, the minutes and grams written to the model with the calculation
 * they came from, or the reason it could not be done. Used by `catalog:slice` for the batches at night and by the
 * admin's "Rozřezat teď" (SliceCatalogModel) after a file is uploaded to a model.
 */
final class CatalogSlicer
{
    public const PARAMS = ['material' => 'PLA', 'quality' => 'standard', 'infill' => 15, 'quantity' => 1];

    public function __construct(private readonly CalculationService $calculations) {}

    /** Slices the model and writes the result to it; returns what went wrong, null when the slice is on the model. */
    public function slice(CatalogModel $model): ?string
    {
        $file = $model->modelFile;
        if (! $file || $file->status === ModelFile::STATUS_DELETED || ! $file->isReady()) {
            return $this->fail($model, $file ? ($file->status === ModelFile::STATUS_FAILED ? 'file_processing_failed' : 'file_not_ready: '.$file->status) : 'file_missing');
        }
        if (! Storage::disk(ModelFile::DISK)->exists((string) $file->stl_path)) {
            return $this->fail($model, 'file_missing_on_disk');
        }
        // the estimate alone, nothing queued (a finished slice of the same file and settings is reused as done)
        $calc = $this->calculations->create($file, self::PARAMS, null, null, null, false);
        if ($calc->status !== Calculation::STATUS_DONE) {
            $calc->status = Calculation::STATUS_QUEUED;
            $calc->save();
            SliceCalculation::dispatchSync($calc->id);
            $calc->refresh();
        }
        $minutes = (int) ($calc->slicer['minutes'] ?? 0);
        $grams = (float) ($calc->slicer['grams'] ?? 0);
        if ($calc->status !== Calculation::STATUS_DONE || $minutes <= 0 || $grams <= 0) {
            return $this->fail($model, mb_substr((string) ($calc->error ?: 'slice_failed: '.$calc->status), 0, 120));
        }
        $model->forceFill([
            'slice_minutes' => $minutes, 'slice_grams' => round($grams, 1), 'slice_material' => (string) ($calc->params['material'] ?? self::PARAMS['material']),
            'sliced_at' => now(), 'slice_error' => null, 'slice_calculation_id' => $calc->id,
        ])->save();

        return null;
    }

    private function fail(CatalogModel $model, string $error): string
    {
        $model->forceFill(['slice_error' => mb_substr($error, 0, 120)])->save();

        return $error;
    }
}
