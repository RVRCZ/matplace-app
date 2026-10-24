<?php

namespace App\Domain\Stats;

use App\Models\Event;
use App\Support\Track;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A wave: one machine that opens our pages as many "visitors" (docs/O.md "Vlny"; 23–24 Oct 2026: 495 "people" in a
 * day, each one page of the catalogue or /login, all direct). It runs the page's script, so it passes the browser's
 * confirmation that keeps robots out; what gives it away is that the many visitors are one:
 *
 *   a candidate   a visit that counts as a person's today (Event::people(): confirmed by the browser's script;
 *                 robots nobody confirmed are left as they are and never counted here – 24 Oct 2026 the first
 *                 version took them in, thousands an hour, and a person with a common browser fell into their
 *                 groups), without a referer or campaign (direct), whose session did nothing (no action of
 *                 Track::ACTIONS, no `act`) and opened at most one page;
 *   a wave        within one clock hour, at least MIN candidates share a fingerprint:
 *                 the same browser (User-Agent and language), or the same network (IPv4 /24, IPv6 /48), over many
 *                 different pages – a crawl; a link that was shared brings many people to ONE page, often from one
 *                 mobile network – or the same network on the sign-in pages, which nobody shares.
 *
 * People are never alone in such numbers here (16–69 a day in Oct 2026, a handful an hour), and a person who does
 * anything – a second page, a calculation, a sign-in – is no candidate at all.
 */
final class Waves
{
    public const MIN = 8;

    public const MARK = 'wave';

    /** pages nobody shares a link to: many direct visitors of them from one network in an hour are one machine */
    private const SIGN_IN = '~^(?:/[a-z]{2})?/(?:login|register|forgot-password)$~';

    /**
     * The visits of waves between two moments, by the reason they were found.
     *
     * @return array<int, string> event id → 'browser' or 'network'
     */
    public function find(Carbon $from, Carbon $until, int $min = self::MIN): array
    {
        $min = max(2, $min);
        $candidates = $this->candidates($from, $until);
        $found = [];
        foreach (['browser' => fn ($v) => $v->user_agent.'|'.$v->locale, 'network' => fn ($v) => self::network((string) $v->ip)] as $why => $key) {
            $groups = [];
            foreach ($candidates as $v) {
                $k = $key($v);
                if ($k === '|' || $k === '') {
                    continue;
                }
                $groups[substr((string) $v->created_at, 0, 13).'|'.$k][] = $v;
            }
            foreach ($groups as $visits) {
                if (count($visits) < $min) {
                    continue;
                }
                // many visitors on ONE page is a link that was shared (people, often from one mobile network, which
                // shares its addresses); a crawl walks many pages. Only the sign-in pages are shared by nobody.
                $paths = array_unique(array_map(fn ($v) => (string) $v->path, $visits));
                $signIn = array_filter($paths, fn ($p) => preg_match(self::SIGN_IN, $p) === 1) === $paths;
                if (count($paths) < ceil(count($visits) / 2) && ! ($why === 'network' && $signIn)) {
                    continue;
                }
                foreach ($visits as $v) {
                    $found[$v->id] ??= $why;
                }
            }
        }
        ksort($found);

        return $found;
    }

    /** @return list<object{id: int, session_id: int, created_at: string, locale: ?string, path: string, ip: ?string, user_agent: ?string}> */
    private function candidates(Carbon $from, Carbon $until): array
    {
        $grammar = DB::connection()->getQueryGrammar();
        $visits = $this->people($from, $until)->leftJoin('anonymous_sessions as s', 's.id', '=', 'v.session_id')
            ->where('v.source', 'direct')->whereNull('v.utm')->whereNull('v.meta->act')
            ->selectRaw('v.id, v.session_id, v.created_at, v.locale, s.ip, s.user_agent, '.$grammar->wrap('v.meta->path').' as path')
            ->orderBy('v.id')->get();
        if ($visits->isEmpty()) {
            return [];
        }
        $sessions = $visits->pluck('session_id')->unique()->values();
        // sessions that did something, or opened a second page: people, whatever else they look like
        $busy = collect();
        foreach ($sessions->chunk(500) as $chunk) {
            $busy = $busy->merge(DB::table('events')->whereIn('session_id', $chunk)->whereIn('type', Track::ACTIONS)->distinct()->pluck('session_id'));
            $busy = $busy->merge(DB::table('events')->whereIn('session_id', $chunk)->where('type', Event::PAGE)
                ->groupBy('session_id')->havingRaw('COUNT(DISTINCT '.$grammar->wrap('meta->path').') > 1')->pluck('session_id'));
        }
        $busy = $busy->flip();

        return $visits->reject(fn ($v) => isset($busy[$v->session_id]))->values()->all();
    }

    /**
     * The visits that count as people's (the condition of Event::people()), a wave's own included: the visits a wave
     * may be found among, and the people a recount starts from.
     */
    private function people(Carbon $from, Carbon $until): Builder
    {
        $since = Humans::since();

        return DB::table('events as v')->where('v.type', Event::VISIT)->where('v.created_at', '>=', $from)->where('v.created_at', '<', $until)
            ->whereNotNull('v.session_id')->whereNull('v.meta->staff')
            ->where(fn ($q) => $q->whereNull('v.meta->bot')->orWhere('v.meta->bot', self::MARK))
            ->when($since, fn ($q) => $q->where(fn ($w) => $w->where('v.created_at', '<', $since)->orWhere('v.meta->js', true)->orWhere('v.meta->act', true)));
    }

    /**
     * People per day once the given visits are a wave's (and no other visit is): the recount the overview would show.
     *
     * @param  list<int>  $wave
     * @return array<string, int> day → people
     */
    public function peopleByDay(Carbon $from, Carbon $until, array $wave): array
    {
        $out = [];
        $wave = array_flip($wave);
        foreach ($this->people($from, $until)->orderBy('v.id')->get(['v.id', 'v.session_id', 'v.created_at']) as $v) {
            if (! isset($wave[$v->id])) {
                $out[substr((string) $v->created_at, 0, 10)][$v->session_id] = true;
            }
        }
        ksort($out);

        return array_map('count', $out);
    }

    /** One network: IPv4 by its /24, IPv6 by its /48 (one provider's customer, one data centre's rack). */
    public static function network(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return '';
        }

        return strlen($packed) === 4 ? bin2hex(substr($packed, 0, 3)).'/24' : bin2hex(substr($packed, 0, 6)).'/48';
    }

    /** How many visitors of waves there were between two moments (marked by matplace:stats-wave). */
    public static function count(Carbon $from, Carbon $until): int
    {
        return (int) Event::where('type', Event::VISIT)->where('meta->bot', self::MARK)->where('created_at', '>=', $from)->where('created_at', '<', $until)
            ->distinct()->count('session_id');
    }
}
