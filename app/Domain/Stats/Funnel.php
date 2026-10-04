<?php

namespace App\Domain\Stats;

use App\Models\DesignerProfile;
use App\Models\Event;

/**
 * What visitors do, read from our own `events` (first-party, no consent needed). Three ways through the site, each
 * ending in a result; a download for one's own printer is a result of its own, not a step towards an order.
 *
 *   customer        visit → upload or generate → calculation → paid order
 *   printer owner   visit → a model's page or a tool's output → download
 *   designer        visit → registration → designer profile → import → file for the farm
 *
 * A step counts the visitors (browser sessions) who did it and every step before it, inside the chosen period.
 * The same numbers are broken down by where the visit came from, by language and by tool.
 *
 * Visitors are people (docs/O.md): a session whose visit was a robot's, the staff's, or — since visits are confirmed
 * by the browser — never confirmed, is left out with everything it did.
 */
final class Funnel
{
    public const PERIODS = [7, 30, 90];

    public const SOURCES = ['google', 'seznam', 'bing', 'facebook', 'instagram', 'youtube', 'designer', 'direct', 'other'];

    /** path → steps; a step is the marks that satisfy it */
    public const PATHS = [
        'customer' => [['visit'], ['upload', 'generate'], ['calculation'], ['order_paid']],
        'owner' => [['visit'], ['model_view', 'tool_view', 'calculation', 'generate'], ['download']],
        'designer' => [['visit'], ['register'], ['designer_enabled'], ['designer_import'], ['designer_file_uploaded']],
    ];

    /** what each path ends in */
    public const RESULTS = ['customer' => 'order_paid', 'owner' => 'download', 'designer' => 'designer_file_uploaded'];

    /**
     * @return array{days: int, sessions: int, paths: array<string, list<array{step: string, sessions: int, rate: ?float}>>, by_source: array<string, array<string, int>>, by_locale: array<string, array<string, int>>, tools: array<string, array{visits: int, outputs: int, downloads: int, orders: int}>, designers: list<array<string, mixed>>, daily: array<string, array<string, int>>}
     */
    public function compute(int $days = 30, ?string $source = null, ?string $locale = null, ?string $tool = null): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : 30;
        $sessions = $this->sessions($days);

        // the filters choose whole visits: everything a visitor did stays together
        $sessions = array_filter($sessions, fn (array $s) => ($source === null || $s['source'] === $source)
            && ($locale === null || $s['locale'] === $locale)
            && ($tool === null || isset($s['tools'][$tool])));

        $paths = [];
        foreach (self::PATHS as $path => $steps) {
            $alive = $sessions;
            $previous = null;
            foreach ($steps as $marks) {
                $alive = array_filter($alive, fn (array $s) => (bool) array_intersect_key(array_flip($marks), $s['marks']));
                $count = count($alive);
                $paths[$path][] = ['step' => implode('|', $marks), 'sessions' => $count, 'rate' => $previous === null ? null : ($previous > 0 ? round(100 * $count / $previous, 1) : 0.0)];
                $previous = $count;
            }
        }

        $breakdown = function (string $by) use ($sessions): array {
            $rows = [];
            foreach ($sessions as $s) {
                $key = (string) ($s[$by] ?: 'other');
                $rows[$key] ??= ['sessions' => 0] + array_fill_keys(array_values(self::RESULTS), 0) + ['register' => 0];
                $rows[$key]['sessions']++;
                foreach ([...array_values(self::RESULTS), 'register'] as $mark) {
                    $rows[$key][$mark] += isset($s['marks'][$mark]) ? 1 : 0;
                }
            }
            uasort($rows, fn ($a, $b) => $b['sessions'] <=> $a['sessions']);

            return $rows;
        };

        $tools = [];
        foreach ($sessions as $s) {
            foreach ($s['tools'] as $key => $did) {
                $tools[$key] ??= ['visits' => 0, 'outputs' => 0, 'downloads' => 0, 'orders' => 0];
                $tools[$key]['visits'] += isset($did['view']) || isset($did['visit']) ? 1 : 0;
                $tools[$key]['outputs'] += isset($did['calculation']) || isset($did['generate']) ? 1 : 0;
                $tools[$key]['downloads'] += isset($did['download']) ? 1 : 0;
                $tools[$key]['orders'] += isset($did['order_paid']) ? 1 : 0;
            }
        }
        uasort($tools, fn ($a, $b) => $b['visits'] <=> $a['visits'] ?: $b['outputs'] <=> $a['outputs']);

        return [
            'days' => $days, 'sessions' => count($sessions), 'paths' => $paths,
            'by_source' => $breakdown('source'), 'by_locale' => $breakdown('locale'),
            'tools' => $tools, 'designers' => $this->designers($days),
        ];
    }

    /**
     * What every designer's links brought: the same numbers a designer sees in their own overview, for all of them.
     *
     * @return list<array{slug: string, name: string, ref_visits: int, sessions: int, orders: int, downloads: int, registrations: int}>
     */
    public function designers(int $days): array
    {
        $rows = [];
        foreach (Event::where('created_at', '>=', now()->subDays($days))->whereNotNull('ref_slug')->get(['session_id', 'type', 'ref_slug']) as $e) {
            $r = &$rows[$e->ref_slug];
            $r ??= ['ref_visits' => 0, 'sessions' => [], 'orders' => 0, 'downloads' => 0, 'registrations' => 0];
            $r['ref_visits'] += $e->type === Event::REF_VISIT ? 1 : 0;
            $r['orders'] += $e->type === 'order_paid' ? 1 : 0;
            $r['downloads'] += $e->type === 'download' ? 1 : 0;
            $r['registrations'] += $e->type === 'register' ? 1 : 0;
            if ($e->session_id) {
                $r['sessions'][$e->session_id] = true;
            }
            unset($r);
        }
        $names = DesignerProfile::whereIn('slug', array_keys($rows))->pluck('display_name', 'slug');
        $out = [];
        foreach ($rows as $slug => $r) {
            $out[] = ['slug' => (string) $slug, 'name' => (string) ($names[$slug] ?? $slug), 'sessions' => count($r['sessions'])] + array_diff_key($r, ['sessions' => 1]);
        }
        usort($out, fn ($a, $b) => $b['ref_visits'] <=> $a['ref_visits'] ?: $b['orders'] <=> $a['orders']);

        return $out;
    }

    /**
     * Every visitor of the period with what they did. A visitor is a browser session; an event recorded outside a
     * web request (a queued job that finished a designer's file) has no session, so it joins the last session of
     * its signed-in user, or stands for a visitor of its own when that user was not here in the period at all.
     *
     * @return array<int|string, array{source: string, locale: string, marks: array<string, true>, tools: array<string, array<string, true>>}>
     */
    private function sessions(int $days): array
    {
        $sessions = [];
        $out = [];
        $confirmed = Humans::since();
        $since = now()->subDays($days);
        $ofUser = Event::where('created_at', '>=', $since)->whereNotNull('session_id')->whereNotNull('user_id')
            ->selectRaw('user_id, max(session_id) as session_id')->groupBy('user_id')->pluck('session_id', 'user_id');
        Event::where('created_at', '>=', $since)->where(fn ($q) => $q->whereNotNull('session_id')->orWhereNotNull('user_id'))->orderBy('id')
            ->select(['id', 'created_at', 'session_id', 'user_id', 'type', 'subject_type', 'source', 'locale', 'meta'])
            ->chunk(5000, function ($events) use (&$sessions, &$out, $ofUser, $confirmed) {
                foreach ($events as $e) {
                    $key = $e->session_id ?? $ofUser[$e->user_id] ?? 'u'.$e->user_id;
                    $meta = (array) $e->meta;
                    if (! empty($meta['staff']) || ($e->type === Event::VISIT && (! empty($meta['bot']) || ($confirmed && $e->created_at >= $confirmed && empty($meta['js']) && empty($meta['act']))))) {
                        $out[$key] = true;
                    }
                    $s = &$sessions[$key];
                    // where the visit came from and its language are those of its first event
                    $s ??= ['source' => (string) ($e->source ?: 'other'), 'locale' => (string) ($e->locale ?: 'cs'), 'marks' => ['visit' => true], 'tools' => []];
                    $mark = match (true) {
                        $e->type === Event::VIEW && in_array($e->subject_type, ['designer_model', 'catalog_model'], true) => 'model_view',
                        $e->type === Event::VIEW && $e->subject_type === 'tool' => 'tool_view',
                        default => $e->type,
                    };
                    $s['marks'][$mark] = true;
                    $tool = is_array($e->meta) ? ($e->meta['tool'] ?? null) : null;
                    if (is_string($tool) && $tool !== '') {
                        $s['tools'][$tool][$e->type] = true;
                    }
                    unset($s);
                }
            });

        return array_diff_key($sessions, $out);
    }
}
