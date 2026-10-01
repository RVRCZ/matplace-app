<?php

namespace App\Support;

use App\Domain\Tools\ParametricGenerator;
use Illuminate\Support\Facades\Lang;

/**
 * A tool's page as content: the texts under the form (lang/<locale>/tools_seo/<tool>.php) and the pictures of what
 * the tool makes (public/img/tool-examples/<tool>-<n>.png, drawn by `matplace:tool-examples` from config/tools.php).
 */
final class ToolSeo
{
    /** @return list<string> tools that are on offer and have a page of their own */
    public static function tools(): array
    {
        return array_keys(array_filter((array) config('tools'), fn (array $t) => ! empty($t['available']) && isset($t['seo'])));
    }

    /**
     * @return array{title: string, description: string, h1: string, intro: list<string>, steps: list<array{name: string, text: string}>, faq: list<array{q: string, a: string}>, examples: list<string>}|null
     *                                                                                                                                                                                                         null when the tool has no texts in that language
     */
    public static function texts(string $tool, ?string $locale = null): ?array
    {
        $locale ??= app()->getLocale();
        $key = 'tools_seo.'.$tool;
        // no falling back to another language: a page either has its own text or shows none
        if (! isset(config('tools')[$tool]['seo']) || ! Lang::hasForLocale($key, $locale)) {
            return null;
        }
        $texts = Lang::get($key, [], $locale);

        return is_array($texts) && ! empty($texts['h1']) ? $texts + ['intro' => [], 'steps' => [], 'faq' => [], 'examples' => []] : null;
    }

    /**
     * The pictures that exist, each with its caption.
     *
     * @return list<array{url: string, path: string, caption: string}>
     */
    public static function examples(string $tool, ?string $locale = null): array
    {
        $captions = (array) (self::texts($tool, $locale)['examples'] ?? []);
        $out = [];
        foreach (array_keys((array) (config('tools')[$tool]['seo']['examples'] ?? [])) as $i) {
            $file = self::examplePath($tool, $i);
            if (is_file($file)) {
                $out[] = ['url' => asset('img/tool-examples/'.basename($file)).'?v='.filemtime($file), 'path' => $file, 'caption' => (string) ($captions[$i] ?? '')];
            }
        }

        return $out;
    }

    public static function examplePath(string $tool, int $index): string
    {
        return public_path('img/tool-examples/'.str_replace('_', '-', $tool).'-'.($index + 1).'.png');
    }

    /** The parameters an example is drawn from: the named preset with the example's own values on top. */
    public static function exampleParams(string $tool, array $example): array
    {
        $preset = isset($example['preset']) ? (array) (ParametricGenerator::PRESETS[$tool][$example['preset']] ?? []) : [];

        return (array) ($example['params'] ?? []) + $preset;
    }

    public static function url(string $tool, ?string $locale = null): string
    {
        return localized_route((string) config('tools')[$tool]['route'], [], $locale);
    }
}
