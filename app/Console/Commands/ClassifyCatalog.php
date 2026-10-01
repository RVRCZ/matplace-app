<?php

namespace App\Console\Commands;

use App\Engines\Ai\Assistant;
use App\Jobs\ClassifyModel;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
use Illuminate\Console\Command;

/**
 * Queues the AI classification for models that have no category yet, or whose picture did not fit their title the
 * last time ("mismatch"). One call per model, each booked in ai_calls; what the assistant is not sure about waits
 * for a person in /admin/catalog/review.
 *
 *   php artisan matplace:classify-catalog --dry-run
 *   php artisan matplace:classify-catalog [--limit=200]
 */
class ClassifyCatalog extends Command
{
    protected $signature = 'matplace:classify-catalog {--limit=200 : queue at most this many now} {--dry-run}';

    protected $description = 'Queue the AI classification of models without a category';

    public function handle(Assistant $assistant): int
    {
        $limit = max(1, (int) $this->option('limit'));
        // never asked before, or asked and found not to match (the admin may have changed the picture since)
        $catalog = CatalogModel::shown()->whereNull('ai_category_id')
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('category_id')->whereNull('ai_checked_at'))->orWhere('ai_mismatch', true))
            ->orderByDesc('view_count')->limit($limit)->pluck('id');
        $cards = DesignerModel::where('visible', true)->whereNull('catalog_category_id')->whereNull('ai_checked_at')->limit(max(0, $limit - $catalog->count()))->pluck('id');

        if ($this->option('dry-run')) {
            $this->info("Would queue {$catalog->count()} inspiration models and {$cards->count()} designers' cards.");

            return self::SUCCESS;
        }
        if (! $assistant->available()) {
            $this->error('The assistant is not configured (ANTHROPIC_API_KEY).');

            return self::FAILURE;
        }
        $catalog->each(fn (int $id) => ClassifyModel::dispatch('catalog_model', $id));
        $cards->each(fn (int $id) => ClassifyModel::dispatch('designer_model', $id));
        $this->info("Queued {$catalog->count()} inspiration models and {$cards->count()} designers' cards.");

        return self::SUCCESS;
    }
}
