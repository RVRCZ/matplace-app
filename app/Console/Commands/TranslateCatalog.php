<?php

namespace App\Console\Commands;

use App\Engines\Translate\Translator;
use App\Jobs\TranslateCatalogModel;
use App\Models\CatalogModel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues translations for the part of the inspiration catalogue that is worth it: every model whose prints may be
 * sold (commercial licence, the ones people look for) and the most visited ones. The rest stays in the language of
 * its source; translating all of it would cost money for pages nobody opens.
 *
 *   php artisan matplace:translate-catalog --dry-run
 *   php artisan matplace:translate-catalog [--top=500] [--limit=200]
 */
class TranslateCatalog extends Command
{
    protected $signature = 'matplace:translate-catalog {--top= : how many of the most visited models, besides the printable ones} {--limit= : queue at most this many now} {--dry-run}';

    protected $description = 'Queue translations of inspiration models (commercial licences and the most visited)';

    public function handle(Translator $translator): int
    {
        $top = (int) ($this->option('top') ?? config('catalog.translate_top', 500));
        $candidates = CatalogModel::shown()->whereNotNull('description')
            ->where(fn (Builder $q) => $q->where('license_restricted', false)
                ->orWhereIn('id', CatalogModel::shown()->orderByDesc('view_count')->orderBy('id')->limit($top)->pluck('id')))
            ->orderByDesc('view_count')->get()
            // only those that still miss a language
            ->filter(fn (CatalogModel $m) => count($m->locales()) < 3 || trim((string) ($m->description['cs'] ?? '')) === '')->values();
        if ($limit = (int) $this->option('limit')) {
            $candidates = $candidates->take($limit);
        }

        if ($this->option('dry-run')) {
            $this->info('Would queue '.$candidates->count().' models.');

            return self::SUCCESS;
        }
        if (! $translator->available()) {
            $this->error('The translator is not configured (ANTHROPIC_API_KEY).');

            return self::FAILURE;
        }
        $candidates->each(fn (CatalogModel $m) => TranslateCatalogModel::dispatch($m->id));
        $this->info('Queued '.$candidates->count().' models. The queue worker translates them one by one; every call is in ai_calls.');

        return self::SUCCESS;
    }
}
