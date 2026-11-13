<?php

namespace App\Support;

use App\Domain\Tools\ToolVisibility;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * An article's HTML on its way to the page (docs/AP.md §15).
 *
 * Links to our tools: an article is written once; a tool can be switched off later at /admin/tools and its page then
 * answers 404. A link to such a page stays in the text as plain words (the sentence reads the same) and comes back
 * by itself when the tool is public again. On 13 Nov 2026 four published articles led to /tools/phone-stand and
 * /tools/colors, both switched off.
 *
 * Pictures: the size of a file of ours and lazy loading below the first picture.
 */
final class BlogLinks
{
    /** The article's HTML with links to tools that are not on offer turned into their own words. */
    public static function live(string $html): string
    {
        if (! str_contains($html, '/tools/')) {
            return $html;
        }
        $tools = self::paths();

        return (string) preg_replace_callback(
            '~<a\s[^>]*\bhref="(?:https?://(?:www\.)?matplace\.com)?(?:/(?:en|es))?(/tools/[a-z0-9-]+)/?(?:[?#][^"]*)?"[^>]*>(.*?)</a>~s',
            function (array $m) use ($tools): string {
                $tool = $tools[$m[1]] ?? null;

                // an address that is no tool of ours (a typing error) is left for the link check to name
                return $tool !== null && ! ToolVisibility::isPublic($tool) ? $m[2] : $m[0];
            },
            $html,
        );
    }

    /**
     * The pictures in an article's text: a file of ours gets its width and height (the text under it then does not
     * jump when the picture arrives), every picture after the first loads when the reader comes to it.
     */
    public static function pictures(string $html): string
    {
        if (! str_contains($html, '<img')) {
            return $html;
        }
        $n = 0;

        return (string) preg_replace_callback('~<img\b([^>]*?)\s*/?>~', function (array $m) use (&$n): string {
            $n++;
            $attrs = $m[1];
            if (! preg_match('~\bsrc="([^"]+)"~', $attrs, $src)) {
                return $m[0];
            }
            if (! preg_match('~\b(width|height)=~', $attrs) && ($size = self::size($src[1]))) {
                $attrs .= ' width="'.$size[0].'" height="'.$size[1].'"';
            }
            if ($n > 1 && ! str_contains($attrs, 'loading=')) {
                $attrs .= ' loading="lazy"';
            }
            if (! str_contains($attrs, 'decoding=')) {
                $attrs .= ' decoding="async"';
            }

            return '<img'.$attrs.'>';
        }, $html);
    }

    /** @return array{0: int, 1: int}|null the size of a picture that is a file of this site (public/img, the public storage) */
    public static function size(?string $address): ?array
    {
        $path = (string) parse_url((string) $address, PHP_URL_PATH);
        $host = parse_url((string) $address, PHP_URL_HOST);
        if (($host !== null && $host !== parse_url(url('/'), PHP_URL_HOST)) || str_contains($path, '..')) {
            return null;
        }
        $file = match (true) {
            str_starts_with($path, '/storage/') => Storage::disk('public')->path(substr($path, 9)),
            str_starts_with($path, '/img/') => public_path(ltrim($path, '/')),
            default => null,
        };
        if ($file === null || ! is_file($file)) {
            return null;
        }

        return Cache::remember('picture.size.'.md5($file.'|'.filemtime($file)), 86400, function () use ($file) {
            $size = @getimagesize($file);

            return $size && $size[0] > 0 && $size[1] > 0 ? [(int) $size[0], (int) $size[1]] : null;
        });
    }

    /** @return array<string, string> the Czech path of every tool's page → the tool (config/tools.php) */
    private static function paths(): array
    {
        static $paths = null;
        if ($paths === null) {
            $paths = [];
            foreach ((array) config('tools') as $tool => $t) {
                if (! empty($t['route']) && Route::has($t['route'])) {
                    $path = (string) parse_url(localized_route($t['route'], [], Locales::DEFAULT), PHP_URL_PATH);
                    if (str_starts_with($path, '/tools/')) {
                        $paths[$path] ??= $tool;
                    }
                }
            }
        }

        return $paths;
    }
}
