<?php

namespace App\Jobs;

use App\Domain\Catalog\CatalogSlicer;
use App\Models\CatalogModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * "Rozřezat teď" from /admin/catalog (docs/AN.md §15): one inspiration model sliced from the file the admin uploaded,
 * on the queue so the page does not wait the minute a slice takes. The nightly `catalog:slice` would get to it anyway.
 */
class SliceCatalogModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 400;

    public function __construct(public readonly int $catalogModelId) {}

    public function handle(CatalogSlicer $slicer): void
    {
        $model = CatalogModel::find($this->catalogModelId);
        if (! $model) {
            return;
        }
        if ($error = $slicer->slice($model)) {
            Log::warning('SliceCatalogModel failed', ['id' => $model->id, 'error' => $error]);
        }
    }
}
