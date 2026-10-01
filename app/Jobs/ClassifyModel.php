<?php

namespace App\Jobs;

use App\Domain\Catalog\CategoryClassifier;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Asks the assistant for the category of one model (App\Domain\Catalog\CategoryClassifier); one model, one call. */
class ClassifyModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public int $backoff = 30;

    /** @param  string  $type  catalog_model | designer_model */
    public function __construct(public readonly string $type, public readonly int $id) {}

    public function handle(CategoryClassifier $classifier): void
    {
        $model = $this->type === 'designer_model' ? DesignerModel::find($this->id) : CatalogModel::find($this->id);
        if ($model) {
            $classifier->classify($model);
        }
    }
}
