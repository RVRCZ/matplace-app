<?php

namespace App\Jobs;

use App\Domain\Catalog\CatalogEnricher;
use App\Engines\Exceptions\EngineException;
use App\Models\CatalogModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** One inspiration model gets a text of ours (CatalogEnricher), from the admin's "obohatit vybrané". */
class EnrichCatalogModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public int $backoff = 60;

    public function __construct(public readonly int $catalogModelId, public readonly bool $force = false)
    {
        // batches over the catalogue wait behind customers' work: the worker takes `ai` only when `default` is empty
        $this->onQueue('ai');
    }

    public function handle(CatalogEnricher $enricher): void
    {
        $model = CatalogModel::find($this->catalogModelId);
        if (! $model || ($model->isEnriched() && ! $this->force)) {
            return;
        }
        try {
            $enricher->enrich($model);
        } catch (EngineException $e) {
            Log::warning('Catalog enrichment failed', ['model' => $model->id, 'error' => $e->getMessage()]);
        }
    }
}
