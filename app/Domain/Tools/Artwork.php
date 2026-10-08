<?php

namespace App\Domain\Tools;

use App\Domain\Farm\Palette;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures the tools turn into shapes (logo, stamp, stencil, illuminated sign, cookie cutter). Three sources, one
 * reference the form sends along with the numbers:
 *
 *   "lib:<category>/<slug>"  a silhouette of our library (engines/artwork, CC0 / public domain only: SOURCES.md)
 *   "<uuid>"                 an upload of this visitor (storage/app/artwork/<owner>/, kept 30 days: "my pictures")
 *   "file:<uuid>"            the copy stored with a created model (kept with it)
 */
final class Artwork
{
    public const KEEP_DAYS = 30;

    public const CATEGORIES = ['colour', 'animals', 'hearts-stars', 'sport', 'jobs', 'holidays', 'cookies', 'transport', 'nature', 'letters-numbers'];

    public const REF = '/^((file:)?[0-9a-f-]{36}|lib:[a-z-]{2,30}\/[a-z0-9-]{1,60})$/';

    public static function libraryDir(): string
    {
        return base_path('engines/artwork');
    }

    private static function uploadsDir(): string
    {
        return storage_path('app/artwork');
    }

    /** Whose pictures these are: the account when signed in, else this browser's anonymous session. */
    public static function owner(?User $user, ?AnonymousSession $session): ?string
    {
        return $user ? 'u'.$user->id : ($session ? 's'.$session->id : null);
    }

    /** Uploaded SVG or picture → the reference of the upload. */
    public static function store(UploadedFile $file, ?string $owner = null): string
    {
        $id = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension()) === 'svg' ? 'svg' : (['image/png' => 'png', 'image/webp' => 'webp'][$file->getMimeType()] ?? 'jpg');
        $dir = self::uploadsDir().'/'.($owner ?? 'x');
        File::ensureDirectoryExists($dir);
        File::copy($file->getRealPath(), $dir.'/'.$id.'.'.$ext);
        File::put($dir.'/'.$id.'.json', json_encode(['name' => mb_substr((string) $file->getClientOriginalName(), 0, 120), 'at' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE));

        return $id;
    }

    /** Where the picture of a reference lives; null when it is gone or the reference is not one. */
    public static function path(string $ref): ?string
    {
        if (! preg_match(self::REF, $ref)) {
            return null;
        }
        if (str_starts_with($ref, 'lib:')) {
            $path = self::libraryDir().'/'.substr($ref, 4).'.svg';

            return is_file($path) ? str_replace('\\', '/', $path) : null;
        }
        $stored = str_starts_with($ref, 'file:');
        $id = $stored ? substr($ref, 5) : $ref;
        $hit = $stored
            ? File::glob(Storage::disk(ModelFile::DISK)->path('files/'.$id.'/artwork.*'))
            // uploads made before "my pictures" lay in tmp/artwork for a day: still found while they last
            : array_merge(self::pictures(self::uploadsDir().'/*/'.$id.'.*'), File::glob(storage_path('app/tmp/artwork/'.$id.'.*')));

        return $hit ? str_replace('\\', '/', $hit[0]) : null;
    }

    /**
     * The visitor's own uploads of the last 30 days, newest first.
     *
     * @return list<array{ref: string, name: string, at: string}>
     */
    public static function mine(?string $owner): array
    {
        if (! $owner) {
            return [];
        }
        $out = [];
        foreach (self::pictures(self::uploadsDir().'/'.$owner.'/*') as $path) {
            if (filemtime($path) < time() - self::KEEP_DAYS * 86400) {
                continue;
            }
            $id = pathinfo($path, PATHINFO_FILENAME);
            $meta = json_decode((string) @file_get_contents(dirname($path).'/'.$id.'.json'), true) ?: [];
            $out[] = ['ref' => $id, 'name' => (string) ($meta['name'] ?? ''), 'at' => (string) ($meta['at'] ?? date('c', (int) filemtime($path)))];
        }
        usort($out, fn ($a, $b) => strcmp($b['at'], $a['at']));

        return array_slice($out, 0, 60);
    }

    /** The upload of this owner, or null: nobody opens a picture that is not theirs. */
    public static function ownPath(?string $owner, string $id): ?string
    {
        if (! $owner || ! preg_match('/^[0-9a-f-]{36}$/', $id)) {
            return null;
        }
        $hit = self::pictures(self::uploadsDir().'/'.$owner.'/'.$id.'.*');

        return $hit ? $hit[0] : null;
    }

    /** Uploads older than 30 days leave the disk (php artisan matplace:prune). Returns how many files went. */
    public static function prune(bool $dry = false): int
    {
        $gone = 0;
        foreach (File::glob(self::uploadsDir().'/*/*') as $path) {
            if (is_file($path) && filemtime($path) < time() - self::KEEP_DAYS * 86400) {
                $gone++;
                if (! $dry) {
                    @unlink($path);
                }
            }
        }

        return $gone;
    }

    /**
     * The library: every silhouette with its name in the visitor's language, optionally one category and a search.
     *
     * @return array{cats: list<array{id: string, name: string, count: int}>, items: list<array{ref: string, slug: string, cat: string, name: string}>}
     */
    public static function library(string $q = '', string $cat = ''): array
    {
        $all = [];
        foreach (self::CATEGORIES as $category) {
            foreach (File::glob(self::libraryDir().'/'.$category.'/*.svg') as $path) {
                $slug = pathinfo($path, PATHINFO_FILENAME);
                $name = __('artwork.items.'.$slug);
                $all[] = ['ref' => 'lib:'.$category.'/'.$slug, 'slug' => $slug, 'cat' => $category, 'name' => $name === 'artwork.items.'.$slug ? str_replace('-', ' ', $slug) : $name];
            }
        }
        $cats = [];
        foreach (self::CATEGORIES as $category) {
            $n = count(array_filter($all, fn ($i) => $i['cat'] === $category));
            if ($n) {
                $cats[] = ['id' => $category, 'name' => __('artwork.cat.'.$category), 'count' => $n];
            }
        }
        $needle = self::plain($q);
        $items = array_values(array_filter($all, function ($i) use ($needle, $cat) {
            if ($cat !== '' && $i['cat'] !== $cat) {
                return false;
            }
            if ($needle === '') {
                return true;
            }
            $extra = __('artwork.keywords.'.$i['slug']);
            $hay = self::plain($i['name'].' '.$i['slug'].' '.($extra === 'artwork.keywords.'.$i['slug'] ? '' : $extra).' '.__('artwork.cat.'.$i['cat']));

            // every word of the search has to be found somewhere
            return collect(explode(' ', $needle))->filter()->every(fn ($word) => str_contains($hay, $word));
        }));
        usort($items, fn ($a, $b) => strcoll($a['name'], $b['name']));

        return ['cats' => $cats, 'items' => $items];
    }

    /** @return list<string> the pictures matching a glob, without their .json notes */
    private static function pictures(string $pattern): array
    {
        return array_values(array_filter(File::glob($pattern), fn ($p) => is_file($p) && ! str_ends_with($p, '.json')));
    }

    private static function plain(string $text): string
    {
        return Palette::plain(str_replace('-', ' ', $text));
    }
}
