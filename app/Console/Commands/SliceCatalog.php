<?php

namespace App\Console\Commands;

use App\Domain\Catalog\CatalogSlicer;
use App\Models\CatalogModel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The real print time and filament of an inspiration model, from a slice of its own file (docs/AN.md §13): one piece
 * in the calculator's default material, standard quality, 15 % infill. The model's page then names the price in three
 * materials and the time, and search engines get a Product with offers where the licence allows a sale. Only a model
 * whose file we hold (model_file_id) can be sliced; `file_available` alone names no file.
 *
 * Runs in batches at night: --limit models a run, --sleep seconds of rest between two, each slice in this process
 * (no queue), so the farm's own slices are never kept waiting. The indexable models come first (they are the pages
 * that are found), the rest with a file after them, the most visited first within each. A slice of the same file and
 * settings made earlier is reused (CalculationService::create), so a second run over a model costs nothing.
 *
 *   php artisan catalog:slice --dry-run
 *   php artisan catalog:slice --limit=50 --sleep=10
 *   php artisan catalog:slice --ids=12,345 --force
 */
class SliceCatalog extends Command
{
    protected $signature = 'catalog:slice
        {--limit=50 : how many models this run}
        {--sleep=10 : seconds of rest between two models}
        {--ids= : these models only, ids separated by commas}
        {--indexed : the indexable models only, none of the rest}
        {--force : slice again, over a result or an error}
        {--dry-run : list who would be sliced; slice nothing, write nothing}';

    protected $description = 'Slice the files of inspiration models for the price in three materials and the print time on their pages';

    public function handle(CatalogSlicer $slicer): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $sleep = max(0, (int) $this->option('sleep'));
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('ids')))));
        $force = (bool) $this->option('force');

        $shown = CatalogModel::shown();
        $this->line(sprintf('Catalogue: %d shown, %d with a file we hold, %d flagged file_available without a file (nothing to slice), %d sliced, %d failed, %d indexable.',
            $shown->count(), (clone $shown)->whereNotNull('model_file_id')->count(), (clone $shown)->where('file_available', true)->whereNull('model_file_id')->count(),
            (clone $shown)->whereNotNull('sliced_at')->count(), (clone $shown)->whereNotNull('slice_error')->count(), CatalogModel::indexable()->count()));

        [$models, $indexedIds] = $this->candidates($ids, $force, (bool) $this->option('indexed'));
        $this->line(sprintf('Candidates: %d (%d indexable first); this run takes %d.', $models->count(), count($indexedIds), min($limit, $models->count())));
        $models = $models->take($limit)->values();
        if ($models->isEmpty()) {
            return self::SUCCESS;
        }
        $this->table(['id', 'views', 'indexed', 'file', 'title'], $models->take(15)->map(fn (CatalogModel $m) => [
            $m->id, $m->view_count, in_array($m->id, $indexedIds, true) ? 'yes' : '—', $m->modelFile?->status ?? 'missing', mb_substr((string) $m->title, 0, 60),
        ])->all());
        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing sliced, nothing written.');

            return self::SUCCESS;
        }

        $done = $failed = 0;
        $started = microtime(true);
        foreach ($models as $i => $model) {
            $t = microtime(true);
            $note = $slicer->slice($model);
            $model->refresh();
            if ($model->sliced_at !== null && $model->slice_error === null && $note === null) {
                $done++;
                $this->line(sprintf('%d · %s · %s · %s g · %.1f s', $model->id, mb_substr((string) $model->title, 0, 50), self::duration((int) $model->slice_minutes), $model->slice_grams, microtime(true) - $t));
            } else {
                $failed++;
                $this->warn(sprintf('%d · %s · failed: %s', $model->id, mb_substr((string) $model->title, 0, 50), $note ?? $model->slice_error));
            }
            if ($sleep > 0 && $i < $models->count() - 1) {
                sleep($sleep);
            }
        }
        $this->info(sprintf('Done: %d sliced, %d failed, %s in all.', $done, $failed, self::duration((int) round((microtime(true) - $started) / 60))));

        return $failed > 0 && $done === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Who is sliced, in order: the indexable models first, then the rest with a file; the most visited first within
     * each; the ids of the indexable ones beside, for the listing.
     *
     * @param  list<int>  $ids
     * @return array{0: Collection<int, CatalogModel>, 1: list<int>}
     */
    private function candidates(array $ids, bool $force, bool $indexedOnly): array
    {
        $base = fn () => CatalogModel::query()->with('modelFile')->shown()->whereNotNull('model_file_id')
            ->when($ids, fn ($q) => $q->whereIn('catalog_models.id', $ids))
            ->when(! $force, fn ($q) => $q->whereNull('sliced_at')->whereNull('slice_error'))
            ->orderByDesc('view_count')->orderBy('catalog_models.id');
        $indexed = $base()->indexable()->get();
        $indexedIds = $indexed->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($indexedOnly) {
            return [$indexed, $indexedIds];
        }
        $rest = $base()->whereNotIn('catalog_models.id', $indexedIds)->get();

        return [$indexed->concat($rest), $indexedIds];
    }

    /** "1 h 47 min", "12 min". */
    private static function duration(int $minutes): string
    {
        return $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    }
}
