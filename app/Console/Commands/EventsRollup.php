<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the statistics small and anonymous in the long run: events older than 13 months are folded into one row
 * per day, kind, source and language (events_daily: how many, by how many visitors) and deleted. Pages fetched by
 * robots (`crawl`) are many and say little one by one: they are folded after 30 days already. Monthly.
 *
 *   php artisan matplace:events-rollup [--months=13] [--crawl-days=30] [--dry-run]
 */
class EventsRollup extends Command
{
    protected $signature = 'matplace:events-rollup {--months=13 : fold events older than this} {--crawl-days=30 : fold pages fetched by robots older than this} {--dry-run}';

    protected $description = 'Fold old events into the daily summary and delete them';

    public function handle(): int
    {
        $before = now()->subMonths(max(1, (int) $this->option('months')))->startOfDay();
        $crawlBefore = now()->subDays(max(1, (int) $this->option('crawl-days')))->startOfDay();
        $count = Event::where('created_at', '<', $before)->count();
        $crawls = Event::where('type', Event::CRAWL)->where('created_at', '<', $crawlBefore)->where('created_at', '>=', $before)->count();
        if ($this->option('dry-run') || $count + $crawls === 0) {
            $this->info($count + $crawls === 0 ? 'Nothing older than '.$before->toDateString().'.' : "Would fold {$count} events older than {$before->toDateString()} and {$crawls} robot pages older than {$crawlBefore->toDateString()}.");

            return self::SUCCESS;
        }

        $days = $this->fold($before, null);
        $this->info("Folded {$count} events of {$days} days into events_daily.");
        if ($crawls > 0) {
            $days = $this->fold($crawlBefore, Event::CRAWL);
            $this->info("Folded {$crawls} robot pages of {$days} days into events_daily.");
        }

        return self::SUCCESS;
    }

    /** Folds the events (of one type, or all) older than a day; returns how many days it touched. */
    private function fold(Carbon $before, ?string $type): int
    {
        $of = fn () => Event::query()->when($type, fn ($q) => $q->where('type', $type));

        // day by day, so a big backlog never sits in memory at once
        $days = $of()->where('created_at', '<', $before)->selectRaw('DATE(created_at) as day')->distinct()->orderBy('day')->pluck('day');
        foreach ($days as $day) {
            DB::transaction(function () use ($day, $of) {
                $rows = [];
                foreach ($of()->whereDate('created_at', $day)->get(['session_id', 'type', 'source', 'locale', 'subject_type']) as $e) {
                    $key = implode('|', [$e->type, $e->source, $e->locale, $e->subject_type]);
                    $rows[$key] ??= ['type' => $e->type, 'source' => $e->source, 'locale' => $e->locale, 'subject_type' => $e->subject_type, 'events' => 0, 'sessions' => []];
                    $rows[$key]['events']++;
                    if ($e->session_id) {
                        $rows[$key]['sessions'][$e->session_id] = true;
                    }
                }
                foreach ($rows as $r) {
                    $existing = DB::table('events_daily')->where('day', $day)->where('type', $r['type'])->where('source', $r['source'])->where('locale', $r['locale'])->where('subject_type', $r['subject_type'])->first();
                    if ($existing) {
                        // a day folded twice (an interrupted run): the numbers add up
                        DB::table('events_daily')->where('id', $existing->id)->update(['events' => $existing->events + $r['events'], 'sessions' => $existing->sessions + count($r['sessions'])]);
                    } else {
                        DB::table('events_daily')->insert(['day' => $day] + ['sessions' => count($r['sessions'])] + array_diff_key($r, ['sessions' => 1]));
                    }
                }
                $of()->whereDate('created_at', $day)->delete();
            });
        }

        return $days->count();
    }
}
