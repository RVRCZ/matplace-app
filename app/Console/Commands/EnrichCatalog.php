<?php

namespace App\Console\Commands;

use App\Domain\Catalog\CatalogEnricher;
use App\Engines\Exceptions\EngineException;
use App\Models\CatalogModel;
use Illuminate\Console\Command;

/**
 * Texts of our own for the inspiration catalogue (App\Domain\Catalog\CatalogEnricher): the most visited models
 * that could then be indexed, or the ids given. Run it dry first: it says who would get a text and what it would
 * cost, and calls nothing. The real run prints the first five results in full, for Roman to read (docs/AL.md §9).
 *
 *   php artisan catalog:enrich --dry-run
 *   php artisan catalog:enrich --top=5
 *   php artisan catalog:enrich --ids=12,345 --force
 */
class EnrichCatalog extends Command
{
    protected $signature = 'catalog:enrich
        {--top= : the most visited models without a text (default config catalog.enrich.top)}
        {--ids= : these models only, ids separated by commas}
        {--dry-run : say who would be enriched and what it would cost; call nothing, write nothing}
        {--any : also models that could not be indexed afterwards (no file or no price)}
        {--force : write a new text over an existing one}
        {--samples=5 : how many results to print in full}';

    protected $description = 'Write texts of our own for inspiration models with Gemini (what, for whom, how to print it)';

    public function handle(CatalogEnricher $enricher): int
    {
        $top = (int) ($this->option('top') ?? config('catalog.enrich.top', 300));
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('ids')))));
        $any = (bool) $this->option('any');
        $force = (bool) $this->option('force');
        $models = $enricher->candidates($top, $ids, $any, $force);

        $shown = CatalogModel::shown()->count();
        $enriched = CatalogModel::shown()->whereNotNull('enriched_at')->count();
        $indexable = CatalogModel::indexable()->count();
        $ready = CatalogModel::shown()->indexReady()->count();
        $this->line(sprintf('Catalogue: %d shown, %d with a text of ours, %d indexable now (index needs: %s); %d could be indexed once they have a text.',
            $shown, $enriched, $indexable, implode(', ', CatalogModel::indexNeeds()) ?: 'nothing', $ready));
        if (! $ids && ! $any) {
            $skipped = CatalogModel::shown()->when(! $force, fn ($q) => $q->whereNull('enriched_at'))->orderByDesc('view_count')->orderBy('id')->limit($top)->get()
                ->filter(fn (CatalogModel $m) => array_diff($m->indexGaps(), ['enriched']))->count();
            $this->line("Of the {$top} most visited, {$skipped} lack the file or the price and are left out (--any takes them too).");
        }
        $this->line('Candidates: '.$models->count().' (model '.$enricher->model().').');
        if ($models->isEmpty()) {
            return self::SUCCESS;
        }

        $estimate = $enricher->estimateAll($models);
        $this->table(['id', 'views', 'languages', 'lacks', 'title'], $models->take(10)->map(fn (CatalogModel $m) => [
            $m->id, $m->view_count, implode(' ', $m->locales()), implode(' ', array_diff($m->indexGaps(), ['enriched'])) ?: '—', mb_substr($m->title, 0, 60),
        ])->all());
        $this->line(sprintf('Estimate: %d calls, ~%s tokens in, ~%s tokens out, ~%.2f Kč in all (~%.3f Kč a call).',
            $estimate['calls'], number_format($estimate['input_tokens']), number_format($estimate['output_tokens']), $estimate['czk'], $estimate['calls'] ? $estimate['czk'] / $estimate['calls'] : 0));
        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing called, nothing written.');

            return self::SUCCESS;
        }
        if (! $enricher->available()) {
            $this->error('Gemini is not configured (GEMINI_API_KEY).');

            return self::FAILURE;
        }

        $samples = max(0, (int) $this->option('samples'));
        $done = 0;
        $failed = 0;
        $tokens = ['input_tokens' => 0, 'output_tokens' => 0];
        $czk = 0.0;
        foreach ($models as $i => $model) {
            try {
                $r = $enricher->enrich($model);
            } catch (EngineException $e) {
                $failed++;
                $this->warn("#{$model->id} {$model->title}: ".$e->getMessage());

                continue;
            }
            $done++;
            $tokens['input_tokens'] += $r['usage']['input_tokens'];
            $tokens['output_tokens'] += $r['usage']['output_tokens'];
            $czk += $r['cost_czk'];
            if ($i < $samples) {
                $this->newLine();
                $this->info("#{$model->id} {$model->title} (".$model->view_count.' views'.($r['price_czk'] !== null ? ', '.$r['price_czk'].' Kč' : ', no price').', '.$r['usage']['input_tokens'].'+'.$r['usage']['output_tokens'].' tokens, '.number_format($r['cost_czk'], 3).' Kč)');
                foreach ($r['texts'] as $locale => $text) {
                    $this->line("[{$locale}] ".str_replace("\n\n", "\n", $text));
                }
            } elseif ($i % 10 === 0) {
                $this->line("… {$done} done, {$failed} failed");
            }
        }
        $this->newLine();
        $this->info(sprintf('Written: %d, failed: %d; %s tokens in, %s tokens out, %.2f Kč (ai_calls, kind "%s").', $done, $failed, number_format($tokens['input_tokens']), number_format($tokens['output_tokens']), $czk, CatalogEnricher::KIND));
        $this->line('Indexable now: '.CatalogModel::indexable()->count().'. Run `php artisan matplace:sitemap` to publish them.');

        return $failed && ! $done ? self::FAILURE : self::SUCCESS;
    }
}
