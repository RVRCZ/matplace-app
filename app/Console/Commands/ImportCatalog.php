<?php

namespace App\Console\Commands;

use App\Models\CatalogCategory;
use App\Models\CatalogModel;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Support\LanguageGuess;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Brings the old site's catalogue over as the inspiration catalogue: categories, models (with the SAME slugs,
 * so /model/{slug} keeps its address), thumbnails and collections. Reads the old database only (connection
 * "legacy", LEGACY_DB_*), never writes there. Can be run again and again: rows are matched by their old id.
 *
 *   php artisan matplace:import-catalog --dry-run
 *   php artisan matplace:import-catalog [--since=2026-09-01] [--thumbs=/var/www/matplace/public/assets/thumbs]
 *
 * Left behind: models that are not active, and adult content.
 */
class ImportCatalog extends Command
{
    protected $signature = 'matplace:import-catalog {--connection=legacy} {--dry-run} {--since=} {--thumbs=} {--assets=}';

    protected $description = 'Import the old catalogue (categories, models, thumbnails, collections) as the inspiration catalogue';

    private const SOURCES = ['printables', 'makerworld', 'makeronline', 'cults3d', 'thingiverse', 'myminifactory', 'drive', 'own'];

    private const MAX_IMAGES = 6;

    public function handle(): int
    {
        $legacy = DB::connection($this->option('connection'));
        $dry = (bool) $this->option('dry-run');
        $since = $this->option('since') ? (string) $this->option('since') : null;
        $thumbs = rtrim((string) ($this->option('thumbs') ?: config('catalog.legacy_thumbs')), '/\\');
        $assets = rtrim((string) ($this->option('assets') ?: config('catalog.legacy_assets_url')), '/');
        $columns = $legacy->getSchemaBuilder()->getColumnListing('models');

        $categories = $this->categories($legacy, $dry);
        $this->line(($dry ? 'categories to import: ' : 'categories: ').count($categories));

        $query = $legacy->table('models')->where('status', 'active')
            ->when(in_array('nsfw', $columns, true), fn ($q) => $q->where('nsfw', 0))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderBy('id');
        $gallery = [];
        foreach ($legacy->table('model_images')->orderBy('model_id')->orderByDesc('is_main')->orderBy('sort_order')->get(['model_id', 'path']) as $image) {
            $gallery[$image->model_id][] = (string) $image->path;
        }

        $done = $skipped = $copied = 0;
        $seen = [];
        $bar = $this->output->createProgressBar((clone $query)->count());
        foreach ($query->cursor() as $r) {
            $bar->advance();
            $title = trim((string) $r->title);
            $slug = trim((string) $r->slug);
            if ($title === '' || $slug === '') {
                $skipped++;

                continue;
            }
            $done++;
            $seen[] = (int) $r->id;
            if ($dry) {
                continue;
            }

            $existing = CatalogModel::where('legacy_id', $r->id)->first();
            $text = self::clean((string) ($r->description ?? ''));
            $locale = $text !== '' ? LanguageGuess::of($text) : null;
            // translations made here stay; only the text of the source is refreshed
            $description = $text !== '' ? [$locale => mb_substr($text, 0, 6000)] + (array) ($existing?->description ?? []) : (array) ($existing?->description ?? []);
            $tags = self::tags($r->tags ?? null);
            $thumb = trim((string) ($r->thumbnail ?? ''));
            [$thumbPath, $n] = $this->copy($thumb, $thumbs);
            $copied += $n;
            $images = [];
            foreach (array_slice(array_values(array_diff($gallery[$r->id] ?? [], [$thumb])), 0, self::MAX_IMAGES) as $path) {
                [$stored, $n] = $this->copy($path, $thumbs);
                $copied += $n;
                if ($stored) {
                    $images[] = $stored;
                }
            }
            $license = (string) ($r->license ?? '') ?: null;

            CatalogModel::updateOrCreate(['legacy_id' => $r->id], [
                'slug' => mb_substr($slug, 0, 270),
                'title' => mb_substr($title, 0, 255),
                'description' => $description ?: null,
                'source_locale' => $locale ?? $existing?->source_locale,
                'keywords' => mb_substr(trim(implode(' ', array_filter([(string) ($r->keywords_cs ?? ''), implode(' ', $tags)]))), 0, 4000) ?: null,
                'source' => in_array($r->source, self::SOURCES, true) ? $r->source : 'other',
                'external_id' => self::externalId((string) ($r->external_url ?? '')),
                'external_url' => $r->external_url ?: null,
                'preview_url' => $thumb === '' ? null : (str_starts_with($thumb, 'http') ? $thumb : $assets.'/assets/thumbs/'.ltrim($thumb, '/')),
                'thumbnail_path' => $thumbPath ?? $existing?->thumbnail_path,
                'images' => $images ?: $existing?->images,
                'license' => $license,
                'license_restricted' => CatalogModel::restricts($license),
                'author_name' => $r->author_name ?: null,
                'category_id' => $categories[(int) ($r->category_id ?? 0)] ?? null,
                'tags' => $tags ?: null,
                'view_count' => max((int) ($existing?->view_count ?? 0), (int) ($r->view_count ?? 0)),
                'est_grams' => (int) ($r->est_grams_slicer ?? 0) ?: ((int) ($r->est_grams_ai ?? 0) ?: null),
                'est_minutes' => (int) ($r->print_time_min ?? 0) ?: ((int) ($r->est_minutes_ai ?? 0) ?: null),
                'max_mm' => (int) ($r->assumed_max_mm ?? 0) ?: null,
                'visible' => true,
            ]);
        }
        $bar->finish();
        $this->newLine();

        // a full run also takes down what the old site no longer shows (hidden, pending, adult)
        $hidden = 0;
        if (! $dry && ! $since) {
            $hidden = CatalogModel::whereNotNull('legacy_id')->where('visible', true)->whereNotIn('legacy_id', $seen ?: [0])->update(['visible' => false]);
        }
        $collections = $this->collections($legacy, $dry);

        $this->info(sprintf('%s %d models (skipped %d without a title or slug), %d pictures copied, %d hidden, %d collections.', $dry ? 'Would import' : 'Imported', $done, $skipped, $copied, $hidden, $collections));
        if ($dry) {
            $this->comment('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int> old category id → new id (in a dry run: old id → 0)
     */
    private function categories(Connection $legacy, bool $dry): array
    {
        $rows = $legacy->table('categories')->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order')->orderBy('id')->get();
        $map = [];
        foreach ($rows as $c) {
            if (! empty($c->hidden)) {
                continue;
            }
            if ($dry) {
                $map[(int) $c->id] = 0;

                continue;
            }
            $existing = CatalogCategory::where('legacy_id', $c->id)->first();
            $name = array_filter(['cs' => trim((string) $c->name), 'en' => trim((string) ($c->name_en ?? ''))]) + (array) ($existing?->name ?? []);
            $category = CatalogCategory::updateOrCreate(['legacy_id' => $c->id], [
                'slug' => (string) $c->slug, 'name' => $name, 'position' => (int) ($c->sort_order ?? 0),
                'parent_id' => $c->parent_id ? ($map[(int) $c->parent_id] ?? null) : null,
            ]);
            $map[(int) $c->id] = $category->id;
        }

        return $map;
    }

    private function collections(Connection $legacy, bool $dry): int
    {
        if (! $legacy->getSchemaBuilder()->hasTable('model_collections')) {
            return 0;
        }
        $rows = $legacy->table('model_collections')->orderBy('id')->get();
        if ($dry) {
            return $rows->count();
        }
        foreach ($rows as $c) {
            $existing = Collection::where('legacy_id', $c->id)->first();
            $collection = Collection::updateOrCreate(['legacy_id' => $c->id], [
                'slug' => (string) $c->slug,
                'title' => ['cs' => trim((string) $c->title)] + (array) ($existing?->title ?? []),
                'description' => array_filter(['cs' => self::clean((string) ($c->description ?? ''))]) + (array) ($existing?->description ?? []) ?: null,
            ]);
            // the old site kept the order on the model row; items picked here by hand (designer cards) are not touched
            $members = $legacy->table('models')->where('collection_id', $c->id)->where('status', 'active')->orderBy('collection_order')->orderBy('id')->pluck('id');
            $ids = CatalogModel::whereIn('legacy_id', $members)->pluck('id', 'legacy_id');
            CollectionItem::where('collection_id', $collection->id)->whereNotNull('catalog_model_id')->whereNotIn('catalog_model_id', $ids->values())->delete();
            foreach ($members as $position => $legacyId) {
                if (isset($ids[$legacyId])) {
                    CollectionItem::updateOrCreate(['collection_id' => $collection->id, 'catalog_model_id' => $ids[$legacyId]], ['position' => $position]);
                }
            }
        }

        return $rows->count();
    }

    /**
     * Copy one picture of the old site into our public storage (catalog/…), keeping its relative path.
     *
     * @return array{0: ?string, 1: int} [path on the public disk or null, 1 when a file was copied now]
     */
    private function copy(string $relative, string $from): array
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || $from === '' || str_starts_with($relative, 'http') || str_contains($relative, '..')) {
            return [null, 0];
        }
        $source = $from.'/'.$relative;
        if (! is_file($source)) {
            return [null, 0];
        }
        $stored = 'catalog/'.$relative;
        $target = Storage::disk('public')->path($stored);
        if (is_file($target) && filesize($target) === filesize($source)) {
            return [$stored, 0];
        }
        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);

        return [$stored, 1];
    }

    /** The old descriptions are source texts with the markup stripped: entities and runs of spaces are tidied. */
    public static function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s*\n\s*/', "\n", $text));
    }

    /** @return list<string> */
    private static function tags(?string $tags): array
    {
        if (! $tags) {
            return [];
        }
        $list = json_decode($tags, true);
        $list = is_array($list) ? $list : preg_split('/[,;]/', $tags);

        return array_slice(array_values(array_unique(array_filter(array_map(fn ($t) => mb_substr(trim((string) $t), 0, 40), (array) $list)))), 0, 20);
    }

    private static function externalId(string $url): ?string
    {
        if (preg_match('~printables\.com/model/(\d+)~', $url, $m) || preg_match('~makerworld\.com/[a-z]{2}/models/(\d+)~', $url, $m) || preg_match('~/(\d+)(?:[/?#-]|$)~', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
