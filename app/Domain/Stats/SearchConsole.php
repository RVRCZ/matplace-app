<?php

namespace App\Domain\Stats;

use App\Models\SearchConsoleRow;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Search Console beside our own numbers on the tools page of the admin (docs/AN.md §12.3, §14): pages with their
 * impressions and clicks, read into the numbers of each tool (by the page's address, in every language) so that
 * impressions → clicks → use stand side by side. Two sources, the same shape: the rows `searchconsole:pull` keeps in
 * the database (the API, by day and country), and, while there are none, the newest CSV export in docs/data
 * (search-console-<date>.csv, Roman's hand export; the columns are found by their names, English or Czech, the
 * separator by the first line).
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

    /** The country filter of the tools page: everything, Czechia, the rest of the world. */
    public const COUNTRIES = ['all', 'cz', 'other'];

    /** Czechia as the API spells it (ISO 3166-1 alpha-3, lower case). */
    public const HOME = 'cze';

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
     * What the tools page shows: the API's rows for the period and country when the database has any, else the newest
     * CSV export (`source` says which). Null without either.
     *
     * @return array{source: string, file?: string, from?: string, until?: string, rows: int, tools: array<string, array{impressions: int, clicks: int, position: ?float, queries: list<array{query: string, clicks: int, impressions: int}>, by_locale: array<string, array{impressions: int, clicks: int}>}>, other: array<string, array{impressions: int, clicks: int}>, total: array{impressions: int, clicks: int}}|null
     */
    public static function current(\DateTimeInterface $from, \DateTimeInterface $to, string $country = 'all'): ?array
    {
        $db = self::fromDatabase($from, $to, $country);
        if ($db !== null) {
            return $db;
        }
        $csv = self::read();

        return $csv ? ['source' => 'csv'] + $csv : null;
    }

    /**
     * The rows of `searchconsole:pull` between two days, for one country or all: the pages' own totals (rows without a
     * query) count, the rows with a query only name the queries. Null while the database holds nothing at all.
     */
    public static function fromDatabase(\DateTimeInterface $from, \DateTimeInterface $to, string $country = 'all'): ?array
    {
        if (! SearchConsoleRow::query()->exists()) {
            return null;
        }
        $base = fn () => SearchConsoleRow::query()->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->when($country === 'cz', fn ($q) => $q->where('country', self::HOME))
            ->when($country === 'other', fn ($q) => $q->where(fn ($w) => $w->where('country', '!=', self::HOME)->orWhereNull('country')));
        $pages = $base()->whereNull('query')->select('page', DB::raw('SUM(clicks) as clicks'), DB::raw('SUM(impressions) as impressions'), DB::raw('SUM(position * impressions) as weighted'))->groupBy('page')->get();
        $queries = $base()->whereNotNull('query')->select('page', 'query', DB::raw('SUM(clicks) as clicks'), DB::raw('SUM(impressions) as impressions'))->groupBy('page', 'query')->get();
        $rows = [];
        foreach ($pages as $p) {
            $rows[] = ['page' => (string) $p->page, 'query' => '', 'clicks' => (int) $p->clicks, 'impressions' => (int) $p->impressions,
                'position' => (int) $p->impressions > 0 ? (float) $p->weighted / (int) $p->impressions : null, 'counts' => true];
        }
        foreach ($queries as $q) {
            $rows[] = ['page' => (string) $q->page, 'query' => (string) $q->query, 'clicks' => (int) $q->clicks, 'impressions' => (int) $q->impressions, 'position' => null, 'counts' => false];
        }
        $span = $base()->whereNull('query')->selectRaw('MIN(date) as first, MAX(date) as last')->first();

        return ['source' => 'api', 'from' => (string) ($span?->first ?? $from->format('Y-m-d')), 'until' => (string) ($span?->last ?? ''), 'country' => $country] + self::aggregate($rows, count($pages));
    }

    /**
     * The newest CSV export, read into the same shape (every row counts: an export of pages has the totals, an export of
     * pages with queries has the queries' rows only, which is what Search Console shows there).
     *
     * @return array{file: string, rows: int, tools: array<string, mixed>, other: array<string, mixed>, total: array{impressions: int, clicks: int}}|null
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
        $rows = [];
        while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (count($line) <= $cols['page']) {
                continue;
            }
            $rows[] = [
                'page' => (string) $line[$cols['page']], 'query' => $cols['query'] !== null ? trim((string) ($line[$cols['query']] ?? '')) : '',
                'clicks' => $cols['clicks'] !== null ? self::int($line[$cols['clicks']] ?? '0') : 0, 'impressions' => self::int($line[$cols['impressions']] ?? '0'),
                'position' => $cols['position'] !== null ? self::float($line[$cols['position']] ?? '') : null, 'counts' => true,
            ];
        }
        fclose($fh);

        return ['file' => basename($file)] + self::aggregate($rows, count($rows));
    }

    /**
     * Rows of pages into the numbers of the tools (every language of a tool's page adds up to it, with the languages
     * beside), the other pages by their address, the total of everything that counts.
     *
     * @param  list<array{page: string, query: string, clicks: int, impressions: int, position: ?float, counts: bool}>  $rows
     * @return array{rows: int, tools: array<string, mixed>, other: array<string, mixed>, total: array{impressions: int, clicks: int}}
     */
    private static function aggregate(array $rows, int $count): array
    {
        $slugs = self::slugs();
        $tools = [];
        $other = [];
        $total = ['impressions' => 0, 'clicks' => 0];
        foreach ($rows as $row) {
            if ($row['counts']) {
                $total['impressions'] += $row['impressions'];
                $total['clicks'] += $row['clicks'];
            }
            $tool = self::toolOf($row['page'], $slugs);
            if ($tool === null) {
                if ($row['counts']) {
                    $path = self::pathOf($row['page']);
                    $o = &$other[$path];
                    $o ??= ['impressions' => 0, 'clicks' => 0];
                    $o['impressions'] += $row['impressions'];
                    $o['clicks'] += $row['clicks'];
                    unset($o);
                }

                continue;
            }
            $t = &$tools[$tool];
            $t ??= ['impressions' => 0, 'clicks' => 0, 'position_sum' => 0.0, 'position_n' => 0, 'queries' => [], 'by_locale' => []];
            if ($row['counts']) {
                $t['impressions'] += $row['impressions'];
                $t['clicks'] += $row['clicks'];
                $locale = self::localeOf($row['page']);
                $l = &$t['by_locale'][$locale];
                $l ??= ['impressions' => 0, 'clicks' => 0];
                $l['impressions'] += $row['impressions'];
                $l['clicks'] += $row['clicks'];
                unset($l);
                if ($row['position'] !== null && $row['impressions'] > 0) {
                    $t['position_sum'] += $row['position'] * $row['impressions'];
                    $t['position_n'] += $row['impressions'];
                }
            }
            if ($row['query'] !== '') {
                $q = &$t['queries'][$row['query']];
                $q ??= ['query' => $row['query'], 'clicks' => 0, 'impressions' => 0];
                $q['clicks'] += $row['clicks'];
                $q['impressions'] += $row['impressions'];
                unset($q);
            }
            unset($t);
        }
        foreach ($tools as &$t) {
            $t['position'] = $t['position_n'] > 0 ? round($t['position_sum'] / $t['position_n'], 1) : null;
            unset($t['position_sum'], $t['position_n']);
            usort($t['queries'], fn ($a, $b) => $b['clicks'] <=> $a['clicks'] ?: $b['impressions'] <=> $a['impressions']);
            $t['queries'] = array_slice($t['queries'], 0, 5);
            ksort($t['by_locale']);
        }
        unset($t);
        uasort($tools, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
        uasort($other, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);

        return ['rows' => $count, 'tools' => $tools, 'other' => array_slice($other, 0, 20, true), 'total' => $total];
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

    /** The language of a page by its address: the prefix, or the default without one. */
    public static function localeOf(string $url): string
    {
        $first = explode('/', trim((string) parse_url(trim($url), PHP_URL_PATH), '/'))[0];

        return in_array($first, Locales::PREFIXED, true) ? $first : Locales::DEFAULT;
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
