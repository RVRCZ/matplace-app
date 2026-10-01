<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the statistics small and anonymous in the long run: events older than 13 months are folded into one row
 * per day, kind, source and language (events_daily: how many, by how many visitors) and deleted. Monthly.
 *
 *   php artisan matplace:events-rollup [--months=13] [--dry-run]
 */
class EventsRollup extends Command
{
    protected $signature = 'matplace:events-rollup {--months=13 : fold events older than this} {--dry-run}';

    protected $description = 'Fold old events into the daily summary and delete them';

    public function handle(): int
    {
        $before = now()->subMonths(max(1, (int) $this->option('months')))->startOfDay();
        $old = Event::where('created_at', '<', $before);
        $count = (clone $old)->count();
        if ($this->option('dry-run') || $count === 0) {
            $this->info($count === 0 ? 'Nothing older than '.$before->toDateString().'.' : "Would fold {$count} events older than {$before->toDateString()}.");

            return self::SUCCESS;
        }

        // day by day, so a big backlog never sits in memory at once
        $days = (clone $old)->selectRaw('DATE(created_at) as day')->distinct()->orderBy('day')->pluck('day');
        foreach ($days as $day) {
            DB::transaction(function () use ($day) {
                $rows = [];
                foreach (Event::whereDate('created_at', $day)->get(['session_id', 'type', 'source', 'locale', 'subject_type']) as $e) {
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
                Event::whereDate('created_at', $day)->delete();
            });
        }
        $this->info("Folded {$count} events of {$days->count()} days into events_daily.");

        return self::SUCCESS;
    }
}
