<?php

namespace App\Jobs;

use App\Engines\Translate\Translator;
use App\Models\CatalogModel;
use App\Support\Locales;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gives one model of the inspiration catalogue a short description in the languages it has none in, written from
 * the text of its source (no coupons, no links to other models). With a text in a language, the page exists in it.
 */
class TranslateCatalogModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $backoff = 30;

    public function __construct(public readonly int $catalogModelId)
    {
        // batches over the catalogue wait behind customers' work: the worker takes `ai` only when `default` is empty
        $this->onQueue('ai');
    }

    public function handle(Translator $translator): void
    {
        $model = CatalogModel::find($this->catalogModelId);
        $description = array_filter(array_map(fn ($t) => trim((string) $t), (array) $model?->description), fn ($t) => $t !== '');
        if (! $model || ! $description) {
            return;
        }
        $missing = array_values(array_diff(Locales::SUPPORTED, array_keys($description)));
        if (! $missing) {
            return;
        }
        $from = isset($description[$model->source_locale]) ? $model->source_locale : array_key_first($description);
        $result = $translator->translate($description[$from], $missing, $from, Translator::STYLE_CATALOG, ['kind' => 'translate', 'subject_type' => 'catalog_model', 'subject_id' => $model->id]);
        $model->forceFill(['description' => $description + $result->texts])->save();
    }
}
