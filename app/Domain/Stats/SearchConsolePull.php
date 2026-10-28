<?php

namespace App\Domain\Stats;

use App\Models\SearchConsoleAccount;
use App\Models\SearchConsoleRow;
use Carbon\CarbonInterface;

/**
 * Pulls Search Console into search_console_rows, day by day (docs/AN.md §14): for every day the pages with their
 * country (the page's own totals) and the pages with their queries and country. Idempotent: a row is keyed by day,
 * page, query and country and is written over, so the daily run over the last three days (Search Console finishes a
 * day two to three days later) and a first run over sixteen months use the same path.
 */
final class SearchConsolePull
{
    /** The two shapes of a day: the pages alone (their totals), then the pages with the queries. */
    public const DIMENSIONS = [['page', 'country'], ['page', 'query', 'country']];

    public function __construct(private readonly SearchConsoleClient $client) {}

    /**
     * @param  callable(string $date, int $rows): void|null  $onDay  told after every day
     * @return array{days: int, rows: int, from: string, to: string}
     *
     * @throws SearchConsoleError when the account is missing, has no property, or Google refuses
     */
    public function pull(CarbonInterface $from, CarbonInterface $to, ?callable $onDay = null): array
    {
        $account = SearchConsoleAccount::current();
        if (! $account) {
            throw new SearchConsoleError('not_connected', 'Search Console is not connected.');
        }
        if (! $account->ready()) {
            throw new SearchConsoleError('no_site', 'No property is chosen in /admin/search-console.');
        }
        $site = (string) $account->site_url;
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $days = 0;
        $total = 0;
        try {
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $date = $day->toDateString();
                $n = 0;
                foreach (self::DIMENSIONS as $dimensions) {
                    foreach ($this->client->rows($site, $date, $dimensions) as $batch) {
                        $n += $this->store($date, $dimensions, $batch);
                    }
                }
                $days++;
                $total += $n;
                if ($onDay) {
                    $onDay($date, $n);
                }
            }
        } catch (SearchConsoleError $e) {
            $account->forceFill(['last_error' => mb_substr(now()->format('j. n. Y H:i').' '.$e->getMessage(), 0, 500)])->save();
            throw $e;
        }
        $account->forceFill(['last_pulled_at' => now(), 'last_pulled_from' => $from->toDateString(), 'last_pulled_to' => $to->toDateString(), 'last_error' => null])->save();

        return ['days' => $days, 'rows' => $total, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array{keys: list<string>, clicks: int|float, impressions: int|float, ctr: float, position: float}>  $rows
     */
    private function store(string $date, array $dimensions, array $rows): int
    {
        $hasQuery = in_array('query', $dimensions, true);
        $now = now();
        $out = [];
        foreach ($rows as $row) {
            $keys = array_values((array) ($row['keys'] ?? []));
            $page = mb_substr((string) ($keys[0] ?? ''), 0, 1000);
            if ($page === '') {
                continue;
            }
            $query = $hasQuery ? mb_substr((string) ($keys[1] ?? ''), 0, 500) : null;
            $country = ($c = (string) ($keys[$hasQuery ? 2 : 1] ?? '')) !== '' ? mb_substr(strtolower($c), 0, 3) : null;
            $out[SearchConsoleRow::keyFor($date, $page, $query, $country)] = [
                'row_key' => SearchConsoleRow::keyFor($date, $page, $query, $country), 'date' => $date, 'page' => $page, 'query' => $query, 'country' => $country,
                'clicks' => (int) round((float) ($row['clicks'] ?? 0)), 'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                'ctr' => round((float) ($row['ctr'] ?? 0), 4), 'position' => isset($row['position']) ? round((float) $row['position'], 2) : null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk(array_values($out), 500) as $chunk) {
            SearchConsoleRow::upsert($chunk, ['row_key'], ['clicks', 'impressions', 'ctr', 'position', 'updated_at']);
        }

        return count($out);
    }
}
