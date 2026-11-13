<?php

namespace App\Domain\Stats;

use App\Models\SearchConsoleAccount;
use App\Models\SearchConsoleInspection;
use App\Models\SearchConsoleSitemap;
use App\Support\Sitemaps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * The state of the index in one place (`searchconsole:inspect`, docs/AP.md §14): which addresses to ask Google about,
 * the asking itself under a cap (Google allows 2 000 inspections a day for a property), what the account's sitemaps
 * say, and the summary the command and the weekly report print: indexed / discovered / crawled / unknown.
 *
 * The addresses: first the important ones (config/seo.php `inspect`: route names in Czech and paths by hand), then
 * what the sitemap lists, the ones never asked or asked longest ago first, so that a daily run with a small cap walks
 * the whole sitemap in a few days. An address already asked today is not asked again.
 */
final class SearchConsoleIndex
{
    public function __construct(private readonly SearchConsoleClient $client) {}

    /** @return list<string> the important addresses, in the order of config */
    public function important(): array
    {
        $urls = [];
        foreach ((array) config('seo.inspect.routes', []) as $name) {
            if (Route::has((string) $name)) {
                $urls[] = localized_route((string) $name, [], 'cs');
            }
        }
        foreach ((array) config('seo.inspect.paths', []) as $path) {
            $urls[] = url('/'.ltrim((string) $path, '/'));
        }

        return array_values(array_unique($urls));
    }

    /** @return list<string> every address the built sitemap lists (empty when no sitemap was built on this machine) */
    public function listed(): array
    {
        $dir = Sitemaps::dir();
        $index = @file_get_contents($dir.'/sitemap.xml');
        if ($index === false) {
            return [];
        }
        $urls = [];
        preg_match_all('~<loc>[^<]*/(sitemap-[a-z0-9-]+\.xml)</loc>~', $index, $files);
        foreach ($files[1] as $file) {
            if (($xml = @file_get_contents($dir.'/'.$file)) !== false && preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m)) {
                foreach ($m[1] as $loc) {
                    $urls[] = html_entity_decode($loc, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * The addresses of one run: the important ones, then the sitemap's by how long ago they were asked, without the
     * ones asked today, cut to the cap.
     *
     * @return list<array{url: string, important: bool}>
     */
    public function queue(int $cap, bool $importantOnly = false): array
    {
        $important = $this->important();
        $rest = $importantOnly ? [] : array_values(array_diff($this->listed(), $important));
        $last = SearchConsoleInspection::query()->selectRaw('url_key, MAX(checked_on) as last')->groupBy('url_key')->pluck('last', 'url_key')->all();
        $age = fn (string $url) => (string) ($last[SearchConsoleInspection::keyOf($url)] ?? '');
        // a stable sort by the day last asked: never asked ('') first, the sitemap's own order within a day
        usort($rest, fn (string $a, string $b) => $age($a) <=> $age($b));
        $today = now()->toDateString();
        $out = [];
        foreach ([[$important, true], [$rest, false]] as [$urls, $flag]) {
            foreach ($urls as $url) {
                if (count($out) >= $cap) {
                    break 2;
                }
                if (substr($age($url), 0, 10) !== $today) {
                    $out[] = ['url' => $url, 'important' => $flag];
                }
            }
        }

        return $out;
    }

    /** How many inspections today's rows already used, of the day's own cap (config/seo.php `inspect.daily_cap`). */
    public function leftToday(): int
    {
        return max(0, (int) config('seo.inspect.daily_cap', 1800) - SearchConsoleInspection::whereDate('checked_on', now()->toDateString())->count());
    }

    /**
     * Asks Google about the addresses and keeps the answers. Stops at the first refusal that is not about one address
     * (the quota, a lost grant) and says so; an address Google cannot answer for is kept as `error` and the run goes on.
     *
     * @param  list<array{url: string, important: bool}>  $queue
     * @param  callable(string $url, string $state): void|null  $onUrl
     * @return array{asked: int, stopped: ?string}
     */
    public function inspect(array $queue, ?callable $onUrl = null): array
    {
        $site = $this->site();
        $asked = 0;
        foreach ($queue as $item) {
            $row = ['state' => SearchConsoleInspection::ERROR, 'error' => null];
            try {
                $status = $this->client->inspect($site, $item['url']);
                $row = [
                    'state' => SearchConsoleInspection::stateOf($status),
                    'verdict' => self::cut($status['verdict'] ?? null, 40),
                    'coverage' => self::cut($status['coverageState'] ?? null, 190),
                    'robots_state' => self::cut($status['robotsTxtState'] ?? null, 40),
                    'indexing_state' => self::cut($status['indexingState'] ?? null, 40),
                    'fetch_state' => self::cut($status['pageFetchState'] ?? null, 40),
                    'google_canonical' => self::cut($status['googleCanonical'] ?? null, 1000),
                    'user_canonical' => self::cut($status['userCanonical'] ?? null, 1000),
                    'last_crawl_at' => empty($status['lastCrawlTime']) ? null : Carbon::parse((string) $status['lastCrawlTime'])->utc(),
                    'error' => null,
                ];
            } catch (SearchConsoleError $e) {
                // the quota, the grant, the property: nothing the next address would change
                if ($e->status === 429 || $e->status === 401 || $e->status === 403 || $e->needsReconnect()) {
                    return ['asked' => $asked, 'stopped' => $e->getMessage()];
                }
                $row['error'] = self::cut($e->getMessage(), 190);
            }
            // one row per address and day (whereDate: a date column reads back with a time on some drivers)
            $key = SearchConsoleInspection::keyOf($item['url']);
            $kept = SearchConsoleInspection::where('url_key', $key)->whereDate('checked_on', now()->toDateString())->first() ?? new SearchConsoleInspection(['url_key' => $key, 'checked_on' => now()->toDateString()]);
            $kept->fill($row + ['url' => self::cut($item['url'], 1000), 'important' => $item['important']])->save();
            $asked++;
            if ($onUrl) {
                $onUrl($item['url'], $row['state']);
            }
        }

        return ['asked' => $asked, 'stopped' => null];
    }

    /** The sitemaps the account lists, kept for today. @return int how many */
    public function pullSitemaps(): int
    {
        $n = 0;
        foreach ($this->client->sitemaps($this->site()) as $s) {
            $path = self::cut($s['path'] ?? null, 255);
            if ($path === null) {
                continue;
            }
            $contents = (array) ($s['contents'] ?? []);
            $kept = SearchConsoleSitemap::where('path', $path)->whereDate('checked_on', now()->toDateString())->first() ?? new SearchConsoleSitemap(['checked_on' => now()->toDateString(), 'path' => $path]);
            $kept->fill([
                'is_index' => (bool) ($s['isSitemapsIndex'] ?? false), 'is_pending' => (bool) ($s['isPending'] ?? false),
                'last_submitted_at' => empty($s['lastSubmitted']) ? null : Carbon::parse((string) $s['lastSubmitted'])->utc(),
                'last_downloaded_at' => empty($s['lastDownloaded']) ? null : Carbon::parse((string) $s['lastDownloaded'])->utc(),
                'warnings' => (int) ($s['warnings'] ?? 0), 'errors' => (int) ($s['errors'] ?? 0),
                'submitted' => (int) array_sum(array_map(fn ($c) => (int) ($c['submitted'] ?? 0), $contents)),
                'indexed' => (int) array_sum(array_map(fn ($c) => (int) ($c['indexed'] ?? 0), $contents)),
            ])->save();
            $n++;
        }

        return $n;
    }

    /**
     * The latest answer for every address ever asked, counted by state, the important addresses that are not in the
     * index, and the sitemaps as last listed. Null when nothing was ever asked.
     *
     * @return array{asked_on: ?string, total: int, states: array<string, int>, important: array{total: int, indexed: int, missing: list<array{url: string, state: string, coverage: ?string}>}, sitemaps: list<array{path: string, submitted: int, indexed: int, errors: int, warnings: int, downloaded: ?string, ours: bool}>, sitemaps_on: ?string}|null
     */
    public static function summary(): ?array
    {
        $latest = [];
        foreach (SearchConsoleInspection::query()->orderBy('checked_on')->orderBy('id')->get(['url_key', 'url', 'state', 'coverage', 'important', 'checked_on']) as $row) {
            $latest[$row->url_key] = $row;   // later rows replace earlier ones
        }
        $day = SearchConsoleSitemap::max('checked_on');
        if (! $latest && ! $day) {
            return null;
        }
        $states = array_fill_keys(SearchConsoleInspection::STATES, 0);
        $important = ['total' => 0, 'indexed' => 0, 'missing' => []];
        foreach ($latest as $row) {
            $states[$row->state] = ($states[$row->state] ?? 0) + 1;
            if ($row->important) {
                $important['total']++;
                if ($row->state === SearchConsoleInspection::INDEXED) {
                    $important['indexed']++;
                } else {
                    $important['missing'][] = ['url' => (string) $row->url, 'state' => (string) $row->state, 'coverage' => $row->coverage];
                }
            }
        }
        $own = url('/sitemap.xml');

        return [
            'asked_on' => $latest ? collect($latest)->max(fn ($r) => $r->checked_on->toDateString()) : null,
            'total' => count($latest),
            'states' => $states,
            'important' => $important,
            'sitemaps' => $day ? SearchConsoleSitemap::whereDate('checked_on', substr((string) $day, 0, 10))->orderBy('path')->get()->map(fn (SearchConsoleSitemap $s) => [
                'path' => (string) $s->path, 'submitted' => $s->submitted, 'indexed' => $s->indexed, 'errors' => $s->errors, 'warnings' => $s->warnings,
                'downloaded' => $s->last_downloaded_at?->toDateString(), 'ours' => rtrim((string) $s->path, '/') === $own,
            ])->all() : [],
            'sitemaps_on' => $day ? Carbon::parse($day)->toDateString() : null,
        ];
    }

    /** The words of a state for a person (Czech: the command's summary and the weekly report are the owner's). */
    public static function label(string $state): string
    {
        return [
            SearchConsoleInspection::INDEXED => 'zaindexováno', SearchConsoleInspection::DISCOVERED => 'objeveno, neindexováno',
            SearchConsoleInspection::CRAWLED => 'procházeno, neindexováno', SearchConsoleInspection::UNKNOWN => 'Google adresu nezná',
            SearchConsoleInspection::EXCLUDED => 'vyřazeno (přesměrování, noindex, duplicita…)', SearchConsoleInspection::ERROR => 'dotaz selhal',
        ][$state] ?? $state;
    }

    /**
     * The summary as lines of text, the same for the command and the weekly report.
     *
     * @param  array<string, mixed>  $summary  of self::summary()
     * @return list<string>
     */
    public static function lines(array $summary): array
    {
        $lines = [];
        if ($summary['total'] > 0) {
            $lines[] = sprintf('Stav indexu (%d adres, poslední dotaz %s): %s.', $summary['total'], Carbon::parse((string) $summary['asked_on'])->format('j. n. Y'),
                implode(', ', array_map(fn (string $s, int $n) => self::label($s).' '.$n, array_keys(array_filter($summary['states'])), array_filter($summary['states']))));
            $lines[] = sprintf('Důležité stránky v indexu: %d z %d.', $summary['important']['indexed'], $summary['important']['total']);
            foreach (array_slice($summary['important']['missing'], 0, 15) as $m) {
                $lines[] = sprintf('  mimo index: %s — %s', $m['url'], self::label($m['state']));
            }
        }
        foreach ($summary['sitemaps'] as $s) {
            $lines[] = sprintf('Mapa stránek %s: odesláno %d, zaindexováno %d%s%s%s', $s['path'], $s['submitted'], $s['indexed'],
                $s['errors'] ? ', chyb '.$s['errors'] : '', $s['downloaded'] ? ', staženo '.Carbon::parse($s['downloaded'])->format('j. n. Y') : '',
                $s['ours'] ? '' : ' — stará mapa, smazat v Search Console');
        }

        return $lines;
    }

    private function site(): string
    {
        $account = SearchConsoleAccount::current();
        if (! $account) {
            throw new SearchConsoleError('not_connected', 'Search Console is not connected.');
        }
        if (! $account->ready()) {
            throw new SearchConsoleError('no_site', 'No property is chosen in /admin/search-console.');
        }

        return (string) $account->site_url;
    }

    private static function cut(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
