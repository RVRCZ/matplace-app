<?php

namespace App\Domain\Stats;

use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/**
 * An export of Google Search Console put into docs/data (search-console-<date>.csv, the newest file wins; Roman
 * exports it by hand, docs/AN.md §12.3): pages with their impressions and clicks, with the queries when the export
 * has them. Read into the numbers of each tool (by the page's address, in every language) so that the tools page of
 * the admin shows impressions → clicks → use side by side. The columns are found by their names, English or Czech;
 * the separator by the first line.
 */
final class SearchConsole
{
    private const COLUMNS = [
        'page' => ['page', 'pages', 'top pages', 'url', 'landing page', 'stránka', 'stránky', 'nejlepší stránky', 'adresa'],
        'query' => ['query', 'queries', 'top queries', 'dotaz', 'dotazy', 'nejčastější dotazy'],
        'clicks' => ['clicks', 'kliknutí', 'počet kliknutí'],
        'impressions' => ['impressions', 'zobrazení', 'počet zobrazení'],
        'position' => ['position', 'pozice', 'průměrná pozice', 'avg. position'],
    ];

    public static function dir(): string
    {
        return (string) config('seo.search_console_dir', base_path('docs/data'));
    }

    /** The newest export, by its name: docs/data/search-console-2026-10-27.csv; null without one. */
    public static function latestFile(): ?string
    {
        $files = glob(rtrim(self::dir(), '/\\').'/search-console-*.csv') ?: [];
        rsort($files, SORT_STRING);

        return $files[0] ?? null;
    }

    /**
     * @return array{file: string, rows: int, tools: array<string, array{impressions: int, clicks: int, position: ?float, queries: list<array{query: string, clicks: int, impressions: int}>}>, other: array<string, array{impressions: int, clicks: int}>, total: array{impressions: int, clicks: int}}|null
     */
    public static function read(?string $file = null): ?array
    {
        $file ??= self::latestFile();
        if (! $file || ! is_file($file)) {
            return null;
        }
        $fh = fopen($file, 'r');
        $first = (string) fgets($fh);
        rewind($fh);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $head = array_map(fn ($h) => mb_strtolower(trim((string) $h, " \t\n\r\0\x0B\"\xEF\xBB\xBF")), fgetcsv($fh, 0, $delimiter) ?: []);
        $index = function (string $what) use ($head): ?int {
            foreach ($head as $i => $name) {
                if (in_array($name, self::COLUMNS[$what], true)) {
                    return $i;
                }
            }

            return null;
        };
        $cols = ['page' => $index('page'), 'query' => $index('query'), 'clicks' => $index('clicks'), 'impressions' => $index('impressions'), 'position' => $index('position')];
        if ($cols['page'] === null || $cols['impressions'] === null) {
            fclose($fh);

            return null;
        }
        $slugs = self::slugs();
        $tools = [];
        $other = [];
        $total = ['impressions' => 0, 'clicks' => 0];
        $rows = 0;
        while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (count($line) <= $cols['page']) {
                continue;
            }
            $rows++;
            $impressions = self::int($line[$cols['impressions']] ?? '0');
            $clicks = $cols['clicks'] !== null ? self::int($line[$cols['clicks']] ?? '0') : 0;
            $position = $cols['position'] !== null ? self::float($line[$cols['position']] ?? '') : null;
            $query = $cols['query'] !== null ? trim((string) ($line[$cols['query']] ?? '')) : '';
            $total['impressions'] += $impressions;
            $total['clicks'] += $clicks;
            $tool = self::toolOf((string) $line[$cols['page']], $slugs);
            if ($tool === null) {
                $path = self::pathOf((string) $line[$cols['page']]);
                $o = &$other[$path];
                $o ??= ['impressions' => 0, 'clicks' => 0];
                $o['impressions'] += $impressions;
                $o['clicks'] += $clicks;
                unset($o);

                continue;
            }
            $t = &$tools[$tool];
            $t ??= ['impressions' => 0, 'clicks' => 0, 'position_sum' => 0.0, 'position_n' => 0, 'queries' => []];
            $t['impressions'] += $impressions;
            $t['clicks'] += $clicks;
            if ($position !== null && $impressions > 0) {
                $t['position_sum'] += $position * $impressions;
                $t['position_n'] += $impressions;
            }
            if ($query !== '') {
                $q = &$t['queries'][$query];
                $q ??= ['query' => $query, 'clicks' => 0, 'impressions' => 0];
                $q['clicks'] += $clicks;
                $q['impressions'] += $impressions;
                unset($q);
            }
            unset($t);
        }
        fclose($fh);
        foreach ($tools as &$t) {
            $t['position'] = $t['position_n'] > 0 ? round($t['position_sum'] / $t['position_n'], 1) : null;
            unset($t['position_sum'], $t['position_n']);
            usort($t['queries'], fn ($a, $b) => $b['clicks'] <=> $a['clicks'] ?: $b['impressions'] <=> $a['impressions']);
            $t['queries'] = array_slice($t['queries'], 0, 5);
        }
        unset($t);
        uasort($tools, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
        arsort($other);
        uasort($other, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);

        return ['file' => basename($file), 'rows' => $rows, 'tools' => $tools, 'other' => array_slice($other, 0, 20, true), 'total' => $total];
    }

    /** The last part of every tool's address → the tool; the home page is the calculator. */
    private static function slugs(): array
    {
        $out = [];
        foreach ((array) config('tools') as $key => $tool) {
            $route = (string) ($tool['route'] ?? '');
            if ($route === '' || ! Route::has($route)) {
                continue;
            }
            $path = trim((string) parse_url(route($route), PHP_URL_PATH), '/');
            $slug = $path === '' ? '' : basename($path);
            $out[$slug] = (string) $key;
        }

        return $out;
    }

    /** The address without the language and the host, as the pages keep it. */
    private static function pathOf(string $url): string
    {
        $path = '/'.trim((string) parse_url(trim($url), PHP_URL_PATH), '/');
        $first = explode('/', ltrim($path, '/'))[0];
        if (in_array($first, Locales::PREFIXED, true)) {
            $path = '/'.ltrim(substr(ltrim($path, '/'), strlen($first)), '/');
        }

        return $path === '' ? '/' : $path;
    }

    private static function toolOf(string $url, array $slugs): ?string
    {
        $path = self::pathOf($url);
        if ($path === '/') {
            return $slugs[''] ?? null;
        }
        if (! preg_match('#^/tools/([a-z0-9-]+)/?$#', $path, $m)) {
            return null;
        }

        return $slugs[$m[1]] ?? null;
    }

    private static function int(string $v): int
    {
        return (int) preg_replace('/[^\d-]/', '', $v);
    }

    private static function float(string $v): ?float
    {
        $v = str_replace([' ', "\u{00A0}", '%'], '', $v);
        $v = str_replace(',', '.', $v);

        return is_numeric($v) ? (float) $v : null;
    }
}
