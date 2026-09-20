<?php

namespace App\Jobs;

use App\Domain\Calculation\CalculationService;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\SliceParams;
use App\Models\Calculation;
use App\Models\ModelFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Precise slice for one calculation. Waits (by re-queueing) until the model file is processed. */
class SliceCalculation implements ShouldQueue
{
    use Queueable;

    public int $tries = 40;      // × backoff = up to ~2 minutes of waiting for the file

    public int $backoff = 3;

    public int $timeout = 400;

    public function __construct(public readonly int $calculationId) {}

    public function handle(Slicer $slicer, CalculationService $service): void
    {
        $calc = Calculation::with('modelFile')->find($this->calculationId);
        if (! $calc || $calc->status === Calculation::STATUS_DONE) {
            return;
        }
        $file = $calc->modelFile;
        if (! $file) {
            $calc->update(['status' => Calculation::STATUS_FAILED, 'error' => 'file_missing']);

            return;
        }
        if ($file->status === ModelFile::STATUS_FAILED) {
            $calc->update(['status' => Calculation::STATUS_FAILED, 'error' => 'file_processing_failed']);

            return;
        }
        if (! $file->isReady()) {
            $this->release($this->backoff);

            return;
        }

        // The rough estimate may be missing when the browser could not measure the file (STEP, IGES…):
        // fill it from server geometry so shared links always have a number even if slicing fails.
        if ($calc->rough === null && $file->volume_mm3 !== null) {
            $calc->rough = $service->roughFor((float) $file->volume_mm3, $file->area_mm2, SliceParams::fromArray($calc->params), (int) ($calc->params['quantity'] ?? 1));
        }
        $calc->status = Calculation::STATUS_SLICING;
        $calc->save();

        try {
            $result = $slicer->slice($file->absoluteStlPath(), SliceParams::fromArray(['tree' => $file->wantsTreeSupports()] + $calc->params));
            $service->applySlice($calc, $result, $slicer->name());
        } catch (\Throwable $e) {
            Log::warning('SliceCalculation failed', ['id' => $calc->id, 'error' => $e->getMessage()]);
            $calc->status = Calculation::STATUS_FAILED;
            $calc->error = mb_substr($e->getMessage(), 0, 1000);
            $calc->save();
        }
    }
}
