<?php

namespace App\Console\Commands;

use App\Models\CatalogModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The picture of an indexed model from our own address (docs/AP.md §14). A model whose thumbnail was never copied
 * shows the picture from where the old site lives (legacy.matplace.com) or from the source's picture server; a page
 * search engines keep should not depend on either. This copies that picture into our storage as WebP and sets
 * `thumbnail_path`; the page then serves it from /storage, without a redirect and without another host.
 *
 *   php artisan catalog:own-images --dry-run       how many indexable models have no picture of their own
 *   php artisan catalog:own-images [--limit=200]   copy them (only indexable models; --all for every shown model)
 *
 * Nothing is deleted; a picture that cannot be fetched is left as it is and counted.
 */
class CatalogOwnImages extends Command
{
    protected $signature = 'catalog:own-images {--limit=200 : how many models at most} {--all : every shown model, not only the indexable ones} {--dry-run : only count}';

    protected $description = 'Copy the pictures of indexed catalogue models into our own storage';

    public function handle(): int
    {
        $query = ($this->option('all') ? CatalogModel::shown() : CatalogModel::indexable())
            ->whereNull('catalog_models.thumbnail_path')->whereNotNull('catalog_models.preview_url')->orderBy('catalog_models.id');
        $total = (clone $query)->count();
        if ($this->option('dry-run') || $total === 0) {
            $this->info("{$total} model(s) show a picture from another address.");

            return self::SUCCESS;
        }
        $copied = $failed = 0;
        foreach ($query->limit(max(1, (int) $this->option('limit')))->get() as $model) {
            $path = $this->copy($model);
            if ($path === null) {
                $failed++;
                $this->line("  not copied: #{$model->id} {$model->slug}");

                continue;
            }
            $model->forceFill(['thumbnail_path' => $path])->save();
            $copied++;
        }
        $this->info("{$copied} copied, {$failed} could not be fetched, ".max(0, $total - $copied - $failed).' left.');

        return self::SUCCESS;
    }

    /** The picture as WebP at most 800 px in our public storage; null when it cannot be had or is no picture. */
    private function copy(CatalogModel $model): ?string
    {
        $url = (string) CatalogModel::directPreview($model->preview_url);
        if (! preg_match('#^https://#', $url)) {
            return null;
        }
        try {
            $res = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; matplace-import/1.0)'])->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $res->ok() || strlen($res->body()) > 12_000_000 || ! ($info = @getimagesizefromstring($res->body())) || $info[0] * $info[1] > 60_000_000) {
            return null;
        }
        $image = @imagecreatefromstring($res->body());
        if (! $image) {
            return null;
        }
        imagepalettetotruecolor($image);
        $scale = min(1, 800 / max(imagesx($image), imagesy($image)));
        $small = imagescale($image, max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
        ob_start();
        imagewebp($small ?: $image, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        $path = 'catalog/own/'.substr(sha1($url), 0, 2).'/'.$model->id.'-'.Str::lower(Str::random(6)).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
