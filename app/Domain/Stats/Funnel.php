<?php

namespace App\Domain\Stats;

use App\Models\AnonymousSession;
use App\Models\DesignerProfile;
use App\Models\Event;

/**
 * What visitors do, read from our own `events` (first-party, no consent needed). Three ways through the site, each
 * ending in a result; a download for one's own printer is a result of its own, not a step towards an order.
 *
 *   customer        visit → upload or generate → calculation → paid order
 *   printer owner   visit → a model's page or a tool's output → download
 *   designer        visit → registration → designer profile → import → file for the farm
 *   sales           visit → upload began → uploaded → calculation → price shown → "Print with us" → the registration
 *                   gate → order → payment began → paid (the sales path, docs/AN.md §9)
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

    public const SOURCES = ['google', 'seznam', 'bing', 'facebook', 'instagram', 'youtube', 'designer', 'embed', 'direct', 'other'];

    /**
     * path → steps; a step is the marks that satisfy it. "order" is the audit's way (docs/AUDIT.md bod 12, docs/AJ.md):
     * the model is ready, the price was shown, an order was started, the customer went to pay, the bank confirmed it.
     */
    public const PATHS = [
        'customer' => [['visit'], ['upload', 'generate'], ['calculation'], ['order_paid']],
        'owner' => [['visit'], ['model_view', 'tool_view', 'calculation', 'generate'], ['download']],
        'designer' => [['visit'], ['register'], ['designer_enabled'], ['designer_import'], ['designer_file_uploaded']],
        'order' => [['visit'], ['model_ready'], ['price_shown'], ['order_started', 'order_created'], ['checkout_started'], ['order_paid']],
        // "upload began" = an upload or a refused one (the browser's and the server's refusals, docs/AN.md §9); the registration gate is
        // passed by registering, by landing on /register, or by an order made with an account that was there already; the
        // payment begins with the card (checkout_started) or with a top-up of credit (topup_started, session 2)
        'sales' => [['visit'], ['upload', 'failed:upload'], ['upload'], ['calculation'], ['price_shown'], ['order_started'], ['register', 'register_visit', 'order_created'], ['order_created'], ['checkout_started', 'topup_started'], ['order_paid']],
    ];

    /** How visitors are split for the audit: by the kind of device, new or returning, and the way they came in. */
    public const SPLITS = ['device' => ['mobile', 'desktop', 'unknown'], 'visitor' => ['new', 'returning'], 'entry' => ['file', 'photo', 'text', 'tool', 'model', 'none']];

    /** Where failures are counted, and what counts as an attempt of that step. */
    public const ATTEMPTS = [
        'upload' => ['upload', 'failed:upload'],
        'model' => ['upload', 'generate', 'model_ready', 'failed:model', 'failed:generate'],
        'calculation' => ['calculation', 'price_shown', 'failed:calculation'],
        'checkout' => ['checkout_started', 'topup_started', 'failed:checkout', 'failed:pay'],
        'download' => ['download', 'failed:download'],
    ];

    /** What counts as a failure of each step (a refused upload, a payment the farm refused from credit: `failed:pay`, session 2). */
    public const FAILS = [
        'upload' => ['failed:upload'],
        'model' => ['failed:model', 'failed:generate'],
        'calculation' => ['failed:calculation'],
        'checkout' => ['failed:checkout', 'failed:pay'],
        'download' => ['failed:download'],
    ];

    /** A browser of a phone or a tablet, from the user agent its anonymous session was started with. */
    private const MOBILE = '/Mobi|Android|iPhone|iPad|iPod|Tablet|Opera Mini|IEMobile|Silk/i';

    /** what each path ends in */
    public const RESULTS = ['customer' => 'order_paid', 'owner' => 'download', 'designer' => 'designer_file_uploaded'];

    /**
     * @return array{days: int, sessions: int, paths: array<string, list<array{step: string, sessions: int, rate: ?float}>>, by_source: array<string, array<string, int>>, by_locale: array<string, array<string, int>>, tools: array<string, array{visits: int, outputs: int, downloads: int, orders: int}>, designers: list<array<string, mixed>>, audit: array<string, mixed>}
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
            'tools' => $tools, 'designers' => $this->designers($days), 'audit' => $this->audit($sessions),
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
                    $s ??= ['source' => (string) ($e->source ?: 'other'), 'locale' => (string) ($e->locale ?: 'cs'), 'marks' => ['visit' => true], 'tools' => [], 'visits' => 0, 'entry' => null, 'looked' => null];
                    $mark = match (true) {
                        $e->type === Event::VIEW && in_array($e->subject_type, ['designer_model', 'catalog_model'], true) => 'model_view',
                        $e->type === Event::VIEW && $e->subject_type === 'tool' => 'tool_view',
                        $e->type === 'failed' => 'failed:'.($meta['step'] ?? 'other'),
                        default => $e->type,
                    };
                    $s['marks'][$mark] = true;
                    $s['visits'] += $e->type === Event::VISIT ? 1 : 0;
                    // the registration page reached (the visit that landed there, or a page view the browser confirmed): the gate of the sales path
                    if (($e->type === Event::VISIT || $e->type === Event::PAGE) && ($meta['path'] ?? '') === '/register') {
                        $s['marks']['register_visit'] = true;
                    }
                    // the way in: the first thing the visitor did towards a model; only who did nothing is told by
                    // the first page of a tool or of a model they looked at
                    $s['entry'] ??= match (true) {
                        $e->type === 'upload' => 'file',
                        $e->type === 'generate' => ($meta['kind'] ?? '') === 'text' ? 'text' : 'photo',
                        in_array($e->type, ['calculation', 'download', 'order_started'], true) && ! empty($meta['tool']) => 'tool',
                        $e->type === 'download' || $e->type === 'order_started' => 'model',
                        default => null,
                    };
                    $s['looked'] ??= match ($mark) {
                        'tool_view' => 'tool',
                        'model_view' => 'model',
                        default => null,
                    };
                    $tool = is_array($e->meta) ? ($e->meta['tool'] ?? null) : null;
                    if (is_string($tool) && $tool !== '') {
                        $s['tools'][$tool][$e->type] = true;
                    }
                    unset($s);
                }
            });

        $sessions = array_diff_key($sessions, $out);

        // the device from the user agent the anonymous session began with; returning = an earlier visit of the browser
        $ids = array_filter(array_keys($sessions), 'is_int');
        $agents = [];
        $before = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $agents += AnonymousSession::whereIn('id', $chunk)->pluck('user_agent', 'id')->all();
            $before += array_fill_keys(Event::where('type', Event::VISIT)->where('created_at', '<', $since)->whereIn('session_id', $chunk)->distinct()->pluck('session_id')->all(), true);
        }
        foreach ($sessions as $key => &$s) {
            $agent = (string) ($agents[$key] ?? '');
            $s['device'] = $agent === '' ? 'unknown' : (preg_match(self::MOBILE, $agent) ? 'mobile' : 'desktop');
            $s['visitor'] = $s['visits'] > 1 || isset($before[$key]) ? 'returning' : 'new';
            $s['entry'] ??= $s['looked'] ?? 'none';
        }
        unset($s);

        return $sessions;
    }

    /**
     * The audit's numbers (docs/AJ.md): the order way split by device, new or returning and the way in, and the shares
     * it asks for. Every share carries its sample size: today 6 to 12 people a day, so a conclusion needs weeks.
     *
     * @param  array<int|string, array<string, mixed>>  $sessions
     * @return array{splits: array<string, array<string, array<string, int>>>, shares: array<string, array{n: int, k: int, rate: ?float}>, errors: array<string, array{n: int, k: int, rate: ?float}>}
     */
    private function audit(array $sessions): array
    {
        $has = fn (array $s, array $marks): bool => (bool) array_intersect_key(array_flip($marks), $s['marks']);
        $share = function (callable $base, callable $hit) use ($sessions): array {
            $n = count(array_filter($sessions, $base));
            $k = count(array_filter($sessions, fn ($s) => $base($s) && $hit($s)));

            return ['n' => $n, 'k' => $k, 'rate' => $n > 0 ? round(100 * $k / $n, 1) : null];
        };

        $splits = [];
        foreach (self::SPLITS as $by => $keys) {
            $splits[$by] = array_fill_keys($keys, ['sessions' => 0, 'model' => 0, 'price' => 0, 'started' => 0, 'paid' => 0, 'download' => 0, 'failed' => 0]);
            foreach ($sessions as $s) {
                $row = &$splits[$by][$s[$by]];
                $row['sessions']++;
                $row['model'] += $has($s, ['model_ready']) ? 1 : 0;
                $row['price'] += $has($s, ['price_shown']) ? 1 : 0;
                $row['started'] += $has($s, ['order_started', 'order_created']) ? 1 : 0;
                $row['paid'] += $has($s, ['order_paid']) ? 1 : 0;
                $row['download'] += $has($s, ['download']) ? 1 : 0;
                $row['failed'] += (bool) array_filter(array_keys($s['marks']), fn ($m) => str_starts_with($m, 'failed:')) ? 1 : 0;
                unset($row);
            }
        }

        $errors = [];
        foreach (self::ATTEMPTS as $step => $marks) {
            $errors[$step] = $share(fn ($s) => $has($s, $marks), fn ($s) => $has($s, self::FAILS[$step]));
        }

        return [
            'splits' => $splits,
            'shares' => [
                'price' => $share(fn () => true, fn ($s) => $has($s, ['price_shown'])),
                'paid_of_started' => $share(fn ($s) => $has($s, ['order_started', 'order_created']), fn ($s) => $has($s, ['order_paid'])),
                'download' => $share(fn ($s) => $has($s, self::ATTEMPTS['download']), fn ($s) => $has($s, ['download'])),
                // a top-up of credit begun and finished (topup_started, topup: session 2, docs/AN.md §9)
                'topup' => $share(fn ($s) => $has($s, ['topup_started', 'topup']), fn ($s) => $has($s, ['topup'])),
            ],
            'errors' => $errors,
        ];
    }
}
