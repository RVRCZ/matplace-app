<?php

namespace App\Console\Commands;

use App\Models\CatalogModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports the legacy catalogue (models table, ~8 400 active rows) into catalog_models as a search index.
 * Only metadata: title, description, keywords, preview URL, external link, licence, author. Idempotent (legacy_id).
 *
 *   php artisan matplace:import-catalog [--connection=legacy] [--assets=https://matplace.com]
 */
class ImportCatalog extends Command
{
    protected $signature = 'matplace:import-catalog {--connection=legacy} {--assets=} {--dry-run}';

    protected $description = 'Import the legacy model catalogue as a search index (metadata only)';

    public function handle(): int
    {
        $conn = DB::connection($this->option('connection'));
        $assets = rtrim((string) ($this->option('assets') ?: config('app.legacy_assets_url', 'https://matplace.com')), '/');
        $dry = (bool) $this->option('dry-run');
        $n = 0;
        $skipped = 0;

        $rows = $conn->table('models')->where('status', 'active')->orderBy('id');
        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows->cursor() as $r) {
            $title = trim((string) $r->title);
            if ($title === '') {
                $skipped++;
                continue;
            }
            $n++;
            if ($dry) {
                continue;
            }
            $thumb = trim((string) ($r->thumbnail ?? ''));
            $preview = $thumb === '' ? null : (str_starts_with($thumb, 'http') ? $thumb : $assets.'/assets/thumbs/'.ltrim($thumb, '/'));
            $keywords = trim(implode(' ', array_filter([
                (string) ($r->keywords_cs ?? ''),
                self::tagsToText($r->tags ?? null),
            ])));
            CatalogModel::updateOrCreate(['legacy_id' => $r->id], [
                'title' => mb_substr($title, 0, 255),
                'description' => mb_substr(strip_tags((string) ($r->description ?? '')), 0, 2000) ?: null,
                'keywords' => $keywords !== '' ? mb_substr($keywords, 0, 4000) : null,
                'source' => in_array($r->source, ['printables', 'makerworld', 'makeronline', 'cults3d', 'drive', 'own'], true) ? $r->source : 'other',
                'external_id' => self::externalId((string) ($r->external_url ?? '')),
                'external_url' => $r->external_url ?: null,
                'preview_url' => $preview,
                'license' => $r->license ?: null,
                'author' => $r->author_name ?: null,
                'est_grams' => (int) ($r->est_grams_slicer ?? 0) ?: ((int) ($r->est_grams_ai ?? 0) ?: null),
                'est_minutes' => (int) ($r->print_time_min ?? 0) ?: ((int) ($r->est_minutes_ai ?? 0) ?: null),
                'max_mm' => (int) ($r->assumed_max_mm ?? 0) ?: null,
                'file_available' => false, // files are copied and linked in a later step (own/drive models)
                'active' => true,
            ]);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info(($dry ? 'Would import ' : 'Imported ').$n.' models, skipped '.$skipped.'.');

        return self::SUCCESS;
    }

    private static function tagsToText(?string $tags): string
    {
        if (! $tags) {
            return '';
        }
        $d = json_decode($tags, true);

        return is_array($d) ? implode(' ', array_map('strval', $d)) : str_replace([',', ';'], ' ', $tags);
    }

    private static function externalId(string $url): ?string
    {
        if (preg_match('#printables\.com/model/(\d+)#', $url, $m) || preg_match('#makerworld\.com/[a-z]{2}/models/(\d+)#', $url, $m) || preg_match('#/(\d+)(?:[/?#-]|$)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
