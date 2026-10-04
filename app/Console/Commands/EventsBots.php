<?php

namespace App\Console\Commands;

use App\Domain\Stats\AccessLog;
use App\Models\Event;
use App\Models\MissingPage;
use App\Support\Bots;
use App\Support\Track;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Marks old visits that were a robot's (`meta.bot` = why). Visits recorded before the browser confirmed them
 * (docs/O.md) are judged by what is left of them:
 *
 *   agent      the User-Agent of the visit's anonymous session, or of its line in the web server's log, is a robot's
 *   burst      one address opened many "new visitors" in a day and none of them did anything else — a browser
 *              keeps its cookie, a robot does not
 *   no-assets  (with --logs) whoever fetched the page never fetched a stylesheet or a script of the site
 *
 * Nothing is deleted and no IP address or User-Agent is written anywhere; a visit already confirmed by its browser
 * or by an action is never touched. Running it again changes nothing.
 *
 *   php artisan matplace:events-bots --since=2026-09-01 [--logs="/var/log/nginx/access.log*"] [--missing] [--burst=6] [--dry-run]
 *
 * --missing also fills the list of addresses that were not found (missing_pages) from the log, for the time
 * before the application counted them itself.
 */
class EventsBots extends Command
{
    protected $signature = 'matplace:events-bots {--since= : judge visits from this day (default: 30 days back)}
        {--logs= : access logs of the web server, e.g. "/var/log/nginx/access.log*"}
        {--missing : list addresses that were not found from the logs as well}
        {--burst=6 : this many one-page "visitors" from one address in a day are a robot}
        {--dry-run : only say what would be marked}';

    protected $description = 'Mark old visits that were robots (nothing is deleted)';

    /** how far the log's clock and ours may differ for a line to be the visit's, in seconds */
    private const SLACK = 2;

    public function handle(): int
    {
        $since = $this->option('since') ? Carbon::parse((string) $this->option('since'))->startOfDay() : now()->subDays(30)->startOfDay();
        $dry = (bool) $this->option('dry-run');

        // visits nobody confirmed, with what their anonymous session remembers
        $visits = DB::table('events')->leftJoin('anonymous_sessions', 'anonymous_sessions.id', '=', 'events.session_id')
            ->where('events.type', Event::VISIT)->where('events.created_at', '>=', $since)
            ->whereNull('events.meta->bot')->whereNull('events.meta->js')->whereNull('events.meta->act')
            ->orderBy('events.id')
            ->get(['events.id', 'events.session_id', 'events.created_at', 'events.meta', 'anonymous_sessions.ip', 'anonymous_sessions.user_agent']);
        $total = DB::table('events')->where('type', Event::VISIT)->where('created_at', '>=', $since)->count();
        if ($visits->isEmpty() && ! $this->option('missing')) {
            $this->info("Nothing to judge: {$total} visits since {$since->toDateString()}, none unconfirmed and unmarked.");

            return self::SUCCESS;
        }

        $acted = DB::table('events')->where('created_at', '>=', $since)->whereIn('type', Track::ACTIONS)->whereNotNull('session_id')->distinct()->pluck('session_id')->flip();
        $verdict = [];   // event id → why

        // 1. the name the session's browser gave
        foreach ($visits as $v) {
            if (! isset($acted[$v->session_id]) && $v->session_id !== null && Bots::byAgent($v->user_agent) !== null) {
                $verdict[$v->id] = 'agent';
            }
        }

        // 2. many one-page "visitors" from one address in a day
        $groups = [];
        foreach ($visits as $v) {
            if ($v->ip && ! isset($acted[$v->session_id])) {
                $groups[$v->ip.'|'.substr((string) $v->created_at, 0, 10)][$v->session_id][] = $v->id;
            }
        }
        $burst = max(2, (int) $this->option('burst'));
        $perAddress = [];
        foreach ($visits as $v) {
            $perAddress[(string) $v->ip] = ($perAddress[(string) $v->ip] ?? 0) + 1;
        }
        arsort($perAddress);
        $top = (string) array_key_first($perAddress);
        if ($top !== '' && count($perAddress) > 1 && reset($perAddress) > 0.5 * $visits->count() && ! filter_var($top, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            // every visitor seems to come from one local address: the web server hides them, this sign says nothing
            $this->warn('More than half of the visits come from one local address; the "burst" sign is skipped.');
            $groups = [];
        }
        foreach ($groups as $sessions) {
            // the same visitor coming back is one session however often; only different "visitors" make a burst
            if (count($sessions) >= $burst) {
                foreach (array_merge(...array_values($sessions)) as $id) {
                    $verdict[$id] ??= 'burst';
                }
            }
        }

        // 3. the web server's log: who fetched the page, and did they fetch anything a browser needs
        $missing = [];
        if ($this->option('logs')) {
            $files = AccessLog::files((string) $this->option('logs'));
            if ($files === []) {
                $this->error('No log file matches '.$this->option('logs'));

                return self::FAILURE;
            }
            [$fromLog, $missing] = $this->readLogs($files, $visits, $since);
            $sessionOf = $visits->pluck('session_id', 'id');
            foreach ($fromLog as $id => $why) {
                if (! isset($acted[$sessionOf[$id]])) {
                    $verdict[$id] ??= $why;
                }
            }
        } elseif ($this->option('missing')) {
            $this->error('--missing needs --logs.');

            return self::FAILURE;
        }

        // what it comes to, by day
        $byDay = [];
        foreach ($visits as $v) {
            $day = substr((string) $v->created_at, 0, 10);
            $byDay[$day] ??= ['day' => $day, 'unconfirmed' => 0, 'agent' => 0, 'burst' => 0, 'no-assets' => 0, 'left' => 0];
            $byDay[$day]['unconfirmed']++;
            $byDay[$day][$verdict[$v->id] ?? 'left']++;
        }
        ksort($byDay);
        $this->table(['day', 'unconfirmed visits', 'robot by name', 'robot by burst', 'robot by log', 'left as people'], array_map('array_values', $byDay));
        $this->info(sprintf('%d visits since %s, %d unconfirmed; %d are robots, %d stay.', $total, $since->toDateString(), $visits->count(), count($verdict), $visits->count() - count($verdict)));

        if ($this->option('missing')) {
            $this->info(sprintf('%d addresses that were not found in the log%s.', count($missing), $dry ? '' : ' written to the list'));
        }
        if ($dry) {
            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        foreach (array_chunk(array_keys($verdict), 500) as $ids) {
            foreach (Event::whereIn('id', $ids)->get() as $event) {
                $event->forceFill(['meta' => ['bot' => $verdict[$event->id]] + (array) $event->meta])->save();
            }
        }
        if ($this->option('missing')) {
            $this->storeMissing($missing);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $files
     * @param  Collection<int, object>  $visits
     * @return array{0: array<int, string>, 1: array<string, array{hits: int, bots: int, first: int, last: int, referer: ?string}>}
     */
    private function readLogs(array $files, $visits, Carbon $since): array
    {
        // the moments and pages we are looking for; old visits kept the address as it was asked for
        $wanted = [];
        foreach ($visits as $v) {
            $path = (string) (json_decode((string) $v->meta, true)['path'] ?? '');
            $at = Carbon::parse($v->created_at, 'UTC')->getTimestamp();
            for ($t = $at - self::SLACK; $t <= $at + self::SLACK; $t++) {
                $wanted[$t.'|'.$path][] = $v->id;
            }
        }
        // the application counts missing addresses itself from its first row on: the log speaks for the time before
        $liveFrom = MissingPage::min('live_at');
        $liveFrom = $liveFrom ? Carbon::parse($liveFrom, 'UTC')->getTimestamp() : PHP_INT_MAX;
        $from = $since->getTimestamp();

        $clients = [];      // "ip|agent" → [number, fetched an asset]
        $hits = [];         // visit id → list of [client number, same address as the session]
        $ipOf = $visits->pluck('ip', 'id');
        $missing = [];
        $log = new AccessLog($files);
        foreach ($log->read() as $line) {
            if ($line['time'] < $from - 86400) {
                continue;
            }
            $key = $line['ip'].'|'.$line['agent'];
            $clients[$key] ??= [count($clients), false, $line['agent']];
            if ($line['status'] < 400 && AccessLog::isAsset($line['path'])) {
                $clients[$key][1] = true;
            }
            if ($line['method'] === 'GET' && $line['status'] === 200) {
                foreach ($wanted[$line['time'].'|'.$line['path']] ?? [] as $id) {
                    $hits[$id][] = [$key, $ipOf[$id] === $line['ip']];
                }
            }
            if ($line['status'] === 404 && $line['time'] >= $from && $line['time'] < $liveFrom && in_array($line['method'], ['GET', 'HEAD'], true) && MissingPage::worthKeeping($line['path'])) {
                $m = &$missing[$line['path']];
                $m ??= ['hits' => 0, 'bots' => 0, 'first' => $line['time'], 'last' => $line['time'], 'referer' => null];
                $m['hits']++;
                $m['bots'] += Bots::byAgent($line['agent']) !== null || $line['method'] === 'HEAD' ? 1 : 0;
                $m['last'] = max($m['last'], $line['time']);
                $m['first'] = min($m['first'], $line['time']);
                $host = strtolower((string) parse_url($line['referer'], PHP_URL_HOST));
                if ($host !== '' && ! str_ends_with($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST))) {
                    $m['referer'] = mb_substr($host.parse_url($line['referer'], PHP_URL_PATH), 0, 190);
                }
                unset($m);
            }
        }
        $this->line(sprintf('Log: %d lines in %d files, %d unreadable, %d different clients.', $log->lines, count($files), $log->unreadable, count($clients)));
        if ($log->lines > 0 && $log->unreadable > 0.2 * $log->lines) {
            $this->warn('Many lines could not be read: the log is not in nginx "combined" format; its signs are weak.');
        }

        $verdict = [];
        foreach ($hits as $id => $candidates) {
            // the line from the session's own address when there is one; otherwise every line of that second and page
            $own = array_filter($candidates, fn (array $c) => $c[1]);
            $why = null;
            foreach ($own ?: $candidates as [$key]) {
                $robot = Bots::byAgent($clients[$key][2]) !== null ? 'agent' : ($clients[$key][1] ? null : 'no-assets');
                if ($robot === null) {
                    continue 2;   // a browser that loaded the page properly may have been this visit: it stays
                }
                $why ??= $robot;
            }
            if ($why !== null) {
                $verdict[$id] = $why;
            }
        }

        return [$verdict, $missing];
    }

    /** @param  array<string, array{hits: int, bots: int, first: int, last: int, referer: ?string}>  $missing */
    private function storeMissing(array $missing): void
    {
        foreach ($missing as $path => $m) {
            $row = MissingPage::firstOrNew(['path' => $path]);
            $first = Carbon::createFromTimestampUTC($m['first']);
            $last = Carbon::createFromTimestampUTC($m['last']);
            $row->fill([
                'log_hits' => $m['hits'], 'log_bot_hits' => $m['bots'],
                'referer' => $row->referer ?? $m['referer'],
                'first_at' => $first,   // the log speaks for the time before the application's own count
                'last_at' => $row->last_at && $row->last_at->gt($last) ? $row->last_at : $last,
            ])->save();
        }
    }
}
