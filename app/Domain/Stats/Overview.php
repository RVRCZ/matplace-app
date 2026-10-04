<?php

namespace App\Domain\Stats;

use App\Models\Event;
use App\Models\MissingPage;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One page of answers for the owner: how many people came, from where, in which language, what they looked at,
 * how far they got, what they searched for in vain, which addresses are missing and what the robots did.
 * The same numbers go out by e-mail every week (matplace:stats-report). Definitions: docs/O.md.
 *
 * "People" are visits of people (Event::people()): one per browser session, robots and the staff left out.
 * Everything is counted in the database; a period is the last N whole days and today, and is compared with the
 * N days before it.
 */
final class Overview
{
    public const PERIODS = [7, 30];

    /** steps of the short funnel: what a customer does on the way to a paid print */
    public const STEPS = ['calculation', 'upload', 'order_created', 'order_paid'];

    /**
     * @return array<string, mixed>
     */
    public function compute(int $days = 7, ?Carbon $until = null): array
    {
        $days = max(1, $days);
        $until = ($until ?? now())->copy();
        $from = $until->copy()->subDays($days);
        $before = $from->copy()->subDays($days);

        $people = $this->people($from, $until);
        $previous = $this->people($before, $from);

        return [
            'days' => $days, 'from' => $from, 'until' => $until,
            'confirmed_since' => Humans::since(),
            'people' => $people, 'people_before' => $previous,
            'unconfirmed' => $this->unconfirmed($from, $until),
            'daily' => $this->daily($from, $until),
            'sources' => $this->grouped('source', $from, $until, $before),
            'locales' => $this->grouped('locale', $from, $until, $before),
            'campaigns' => $this->campaigns($from, $until),
            'pages' => $this->pages($from, $until),
            'funnel' => $this->funnel($from, $until, $before, $people, $previous),
            'searches' => $this->searches($from, $until),
            'missing' => $this->missing($from),
            'robots' => $this->robots($from, $until),
        ];
    }

    private function visits(Carbon $from, Carbon $until): Builder
    {
        return Event::people()->where('created_at', '>=', $from)->where('created_at', '<', $until);
    }

    private function people(Carbon $from, Carbon $until): int
    {
        return (int) $this->visits($from, $until)->distinct()->count('session_id');
    }

    /** Visits with a browser's name that no browser confirmed: robots in disguise (and a few people whose script was blocked). */
    private function unconfirmed(Carbon $from, Carbon $until): int
    {
        $since = Humans::since();
        if (! $since) {
            return 0;
        }

        return Event::where('type', Event::VISIT)->where('created_at', '>=', $from->max($since))->where('created_at', '<', $until)
            ->whereNull('meta->bot')->whereNull('meta->staff')->whereNull('meta->js')->whereNull('meta->act')->count();
    }

    /**
     * People per day, every day of the period (also the empty ones), with the robots' pages next to them.
     *
     * @return list<array{day: string, people: int, robots: int}>
     */
    private function daily(Carbon $from, Carbon $until): array
    {
        $people = $this->visits($from, $until)->selectRaw('DATE(created_at) as day, COUNT(DISTINCT session_id) as n')->groupBy('day')->pluck('n', 'day');
        $robots = Event::where('type', Event::CRAWL)->where('created_at', '>=', $from)->where('created_at', '<', $until)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as n')->groupBy('day')->pluck('n', 'day');
        $out = [];
        for ($day = $from->copy()->startOfDay(); $day->lt($until); $day->addDay()) {
            $key = $day->toDateString();
            $out[] = ['day' => $key, 'people' => (int) ($people[$key] ?? 0), 'robots' => (int) ($robots[$key] ?? 0)];
        }

        return $out;
    }

    /**
     * People by where they came from, or by language, with the period before.
     *
     * @return array<string, array{people: int, before: int}>
     */
    private function grouped(string $column, Carbon $from, Carbon $until, Carbon $before): array
    {
        $count = fn (Carbon $a, Carbon $b) => $this->visits($a, $b)->selectRaw($column.' as k, COUNT(DISTINCT session_id) as n')->groupBy($column)->pluck('n', 'k');
        $now = $count($from, $until);
        $then = $count($before, $from);
        $out = [];
        foreach ($now->keys()->merge($then->keys())->unique() as $key) {
            $out[(string) ($key ?: 'other')] = ['people' => (int) ($now[$key] ?? 0), 'before' => (int) ($then[$key] ?? 0)];
        }
        uasort($out, fn ($a, $b) => $b['people'] <=> $a['people'] ?: $b['before'] <=> $a['before']);

        return $out;
    }

    /**
     * People who came through a link with UTM marks (our videos and posts), and what they went on to do.
     *
     * @return list<array{source: string, medium: string, campaign: string, people: int, orders: int}>
     */
    private function campaigns(Carbon $from, Carbon $until): array
    {
        $grammar = DB::connection()->getQueryGrammar();
        $utm = fn (string $key) => $grammar->wrap('utm->utm_'.$key);
        $rows = $this->visits($from, $until)->whereNotNull('utm')
            ->selectRaw($utm('source').' as s, '.$utm('medium').' as m, '.$utm('campaign').' as c, COUNT(DISTINCT session_id) as n')
            ->groupBy('s', 'm', 'c')->orderByDesc('n')->limit(20)->get();
        $out = [];
        foreach ($rows as $r) {
            $paid = Event::where('type', 'order_paid')->where('created_at', '>=', $from)->where('created_at', '<', $until)
                ->where('utm->utm_source', $r->s)->where('utm->utm_campaign', $r->c)->distinct()->count('session_id');
            $out[] = ['source' => (string) $r->s, 'medium' => (string) $r->m, 'campaign' => (string) $r->c, 'people' => (int) $r->n, 'orders' => (int) $paid];
        }

        return $out;
    }

    /**
     * The most opened pages. Page views are reported by the browser (`page`), so they are people's by nature; for
     * the time before that the page a visit landed on stands in.
     *
     * @return array{landing: bool, rows: list<array{path: string, views: int, people: int}>}
     */
    private function pages(Carbon $from, Carbon $until): array
    {
        $path = DB::connection()->getQueryGrammar()->wrap('meta->path');
        $pages = Event::where('type', Event::PAGE)->where('created_at', '>=', $from)->where('created_at', '<', $until)->whereNull('meta->staff');
        $landing = ! (clone $pages)->exists();
        $rows = ($landing ? $this->visits($from, $until) : $pages)
            ->selectRaw($path.' as path, COUNT(*) as views, COUNT(DISTINCT session_id) as people')->groupBy('path')->orderByDesc('people')->orderByDesc('views')->limit(25)->get();

        return ['landing' => $landing, 'rows' => $rows->map(fn ($r) => ['path' => (string) $r->path, 'views' => (int) $r->views, 'people' => (int) $r->people])->all()];
    }

    /**
     * How many visitors did each step (a visitor = a browser session, or the account for what a signed-in customer
     * did outside one), with the share of all people and the period before.
     *
     * @return list<array{step: string, people: int, before: int, rate: ?float}>
     */
    private function funnel(Carbon $from, Carbon $until, Carbon $before, int $people, int $previous): array
    {
        $count = fn (Carbon $a, Carbon $b) => Event::whereIn('type', self::STEPS)->where('created_at', '>=', $a)->where('created_at', '<', $b)->whereNull('meta->staff')
            ->selectRaw('type, COUNT(DISTINCT session_id) + COUNT(DISTINCT CASE WHEN session_id IS NULL THEN user_id END) as n')->groupBy('type')->pluck('n', 'type');
        $now = $count($from, $until);
        $then = $count($before, $from);
        $out = [['step' => 'people', 'people' => $people, 'before' => $previous, 'rate' => null]];
        foreach (self::STEPS as $step) {
            $n = (int) ($now[$step] ?? 0);
            $out[] = ['step' => $step, 'people' => $n, 'before' => (int) ($then[$step] ?? 0), 'rate' => $people > 0 ? round(100 * $n / $people, 1) : null];
        }

        return $out;
    }

    /**
     * What people searched for and our catalogue had nothing for.
     *
     * @return array{total: int, empty: list<array{query: string, searches: int}>}
     */
    private function searches(Carbon $from, Carbon $until): array
    {
        $base = fn () => SearchQuery::where('created_at', '>=', $from)->where('created_at', '<', $until);

        return [
            'total' => $base()->count(),
            'empty' => $base()->where('results_local', 0)->select('query', DB::raw('COUNT(*) as searches'))->groupBy('query')->orderByDesc('searches')->orderBy('query')->limit(15)
                ->get()->map(fn ($r) => ['query' => (string) $r->query, 'searches' => (int) $r->searches])->all(),
        ];
    }

    /**
     * Addresses that were not found, the ones people asked for first: candidates for a redirect in config/legacy.php.
     *
     * @return list<array{path: string, people: int, robots: int, referer: ?string, last: ?Carbon}>
     */
    private function missing(Carbon $from): array
    {
        return MissingPage::where('last_at', '>=', $from)
            ->selectRaw('path, referer, last_at, ((hits - bot_hits) + (log_hits - log_bot_hits)) as people, (bot_hits + log_bot_hits) as robots')
            ->orderByDesc('people')->orderByDesc('robots')->limit(30)->get()
            ->map(fn ($r) => ['path' => (string) $r->path, 'people' => (int) $r->people, 'robots' => (int) $r->robots, 'referer' => $r->referer, 'last' => $r->last_at])->all();
    }

    /**
     * What the robots fetched: pages and how many different addresses, by robot. Good news after a new sitemap —
     * the search engines are reading the site.
     *
     * @return array{pages: int, by: array<string, array{pages: int, addresses: int}>}
     */
    public function robots(Carbon $from, Carbon $until): array
    {
        $path = DB::connection()->getQueryGrammar()->wrap('meta->path');
        $rows = Event::where('type', Event::CRAWL)->where('created_at', '>=', $from)->where('created_at', '<', $until)
            ->selectRaw('source, COUNT(*) as pages, COUNT(DISTINCT '.$path.') as addresses')->groupBy('source')->orderByDesc('pages')->get();
        $by = [];
        foreach ($rows as $r) {
            $by[(string) $r->source] = ['pages' => (int) $r->pages, 'addresses' => (int) $r->addresses];
        }

        return ['pages' => array_sum(array_column($by, 'pages')), 'by' => $by];
    }
}
