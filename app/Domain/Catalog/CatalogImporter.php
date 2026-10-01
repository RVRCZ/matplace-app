<?php

namespace App\Domain\Catalog;

use App\Engines\Import\ImportFailed;
use App\Engines\Import\Sources;
use App\Models\CatalogModel;
use App\Models\DesignerImport;
use App\Support\LanguageGuess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The admin adds models to the inspiration catalogue: picked from a search across the sources, or pasted as a list
 * of addresses. For Printables and MakerWorld the model's own page is read (description, pictures, licence, author);
 * for any other source the card is what the search result said. Only links and pictures are taken, never files.
 *
 * Every run is kept as a designer_imports row without a designer, so the admin sees what happened to each address.
 */
final class CatalogImporter
{
    public const MAX_ITEMS = 50;

    public function __construct(private readonly Sources $sources) {}

    /**
     * @param  list<array{url: string, title?: ?string, preview?: ?string, source?: ?string, author?: ?string, license?: ?string}>  $items
     */
    public function run(array $items): DesignerImport
    {
        $items = array_slice(array_values(array_filter($items, fn ($i) => preg_match('#^https://#', (string) ($i['url'] ?? '')))), 0, self::MAX_ITEMS);
        $import = DesignerImport::create([
            'designer_profile_id' => null, 'source' => 'admin', 'status' => DesignerImport::STATUS_RUNNING, 'total' => count($items),
            'items' => array_map(fn ($i) => ['url' => (string) $i['url'], 'title' => (string) ($i['title'] ?? ''), 'state' => DesignerImport::ITEM_WAITING], $items),
        ]);
        foreach ($items as $index => $item) {
            try {
                [$state, $model, $note] = $this->one($item);
                $import->finishItem($index, $state, $note, $model?->id);
            } catch (ImportFailed $e) {
                $import->finishItem($index, DesignerImport::ITEM_FAILED, $e->reason);
            } catch (\Throwable $e) {
                $import->finishItem($index, DesignerImport::ITEM_FAILED, mb_substr($e->getMessage(), 0, 200));
            }
        }
        if (! $items) {
            $import->update(['status' => DesignerImport::STATUS_DONE]);
        }

        return $import->refresh();
    }

    /**
     * @return array{0: string, 1: ?CatalogModel, 2: ?string} state, the model, a note
     *
     * @throws ImportFailed
     */
    private function one(array $item): array
    {
        $url = self::canonical((string) $item['url']);
        if ($existing = CatalogModel::where('external_url', $url)->first()) {
            return [DesignerImport::ITEM_SKIPPED, $existing, 'duplicate'];
        }
        $known = $this->sources->forModelUrl($url);
        if ($known) {
            // the model's own page: the full card
            [$source, $id] = $known;
            $m = $source->fetch($id);
            $text = $m->descriptionText();
            $data = [
                'title' => $m->title, 'source' => $source->key(), 'external_id' => $m->id, 'external_url' => self::canonical($m->url), 'author_name' => $m->authorName ?: null,
                'license' => License::key($m->license), 'tags' => array_slice($m->tags, 0, 20), 'preview_url' => $m->images[0] ?? null,
                'description' => $text !== '' ? [LanguageGuess::of($text) => $text] : null, 'source_locale' => $text !== '' ? LanguageGuess::of($text) : null,
            ];
            if ($existing = CatalogModel::where('external_url', $data['external_url'])->first()) {
                return [DesignerImport::ITEM_SKIPPED, $existing, 'duplicate'];
            }
        } else {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                throw new ImportFailed('not_found', 'only models of Printables and MakerWorld can be read from an address alone');
            }
            $data = [
                'title' => $title, 'source' => self::sourceOf($url, (string) ($item['source'] ?? '')), 'external_url' => $url, 'author_name' => ($item['author'] ?? null) ?: null,
                'license' => License::key((string) ($item['license'] ?? '')), 'preview_url' => preg_match('#^https://#', (string) ($item['preview'] ?? '')) ? (string) $item['preview'] : null,
            ];
        }
        $data['title'] = mb_substr($data['title'], 0, 250);
        $model = CatalogModel::create($data + [
            'slug' => self::slug($data['title']), 'keywords' => mb_substr(implode(' ', (array) ($data['tags'] ?? [])), 0, 500) ?: null,
            'license_restricted' => CatalogModel::restricts($data['license']), 'visible' => true, 'file_available' => false,
        ]);
        // a copy of the picture on our side, so the page does not depend on the source keeping it
        if ($model->preview_url && ($path = $this->thumbnail($model))) {
            $model->forceFill(['thumbnail_path' => $path])->save();
        }

        return [DesignerImport::ITEM_IMPORTED, $model, null];
    }

    /** The picture of a model from one of the picture servers we know, stored as WebP; null when it cannot be had. */
    private function thumbnail(CatalogModel $model): ?string
    {
        $url = (string) $model->preview_url;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $known = collect((array) config('engines.import.image_hosts'))->contains(fn (string $h) => $host === $h || str_ends_with($host, '.'.$h));
        if (! $known) {
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
        $path = 'catalog/admin/'.substr(sha1($url), 0, 2).'/'.$model->id.'-'.Str::lower(Str::random(6)).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /** The address without tracking parameters and anchors: the same model pasted twice is found again. */
    public static function canonical(string $url): string
    {
        $parts = parse_url(trim($url));
        if (empty($parts['host'])) {
            return trim($url);
        }

        return 'https://'.strtolower($parts['host']).rtrim((string) ($parts['path'] ?? ''), '/');
    }

    private static function sourceOf(string $url, string $said): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (['printables' => 'printables.com', 'makerworld' => 'makerworld.com', 'makeronline' => 'makeronline.com', 'thingiverse' => 'thingiverse.com', 'cults3d' => 'cults3d.com', 'myminifactory' => 'myminifactory.com'] as $key => $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $key;
            }
        }

        return preg_match('/^[a-z0-9_]{2,20}$/', $said) ? $said : 'other';
    }

    public static function slug(string $title): string
    {
        $base = Str::slug($title) ?: 'model';
        $slug = mb_substr($base, 0, 200);
        for ($n = 2; CatalogModel::where('slug', $slug)->exists(); $n++) {
            $slug = mb_substr($base, 0, 200).'-'.$n;
        }

        return $slug;
    }
}
