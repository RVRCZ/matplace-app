<?php

namespace App\Console\Commands;

use App\Domain\Stats\Waves;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Marks the visits of a wave (App\Domain\Stats\Waves, docs/O.md "Vlny") as a robot's: `meta.bot = wave`. They leave
 * the people and are counted as one number of their own. Runs every hour over the last two days (routes/console.php);
 * --since judges older days once (23–24 Oct 2026). A visit whose session did something meanwhile loses its mark.
 * Nothing is deleted; the table says, day by day, how many people there were before and after.
 *
 *   php artisan matplace:stats-wave [--since=2026-10-17] [--until=…] [--min=8] [--dry-run]
 */
class StatsWave extends Command
{
    protected $signature = 'matplace:stats-wave {--since= : judge visits from this moment (default: 48 hours back)}
        {--until= : up to this moment (default: now)} {--min=8 : this many look-alike visitors in an hour are one machine}
        {--dry-run : only say what would change}';

    protected $description = 'Mark the visitors of a wave (one machine passing for many people) as a robot';

    public function handle(Waves $waves): int
    {
        $since = $this->option('since') ? Carbon::parse((string) $this->option('since')) : now()->subHours(48);
        $until = $this->option('until') ? Carbon::parse((string) $this->option('until')) : now();
        $min = (int) $this->option('min');
        $found = $waves->find($since, $until, $min);
        $marked = Event::where('type', Event::VISIT)->where('meta->bot', Waves::MARK)->where('created_at', '>=', $since)->where('created_at', '<', $until)->pluck('id')->all();
        $add = array_values(array_diff(array_keys($found), $marked));
        $drop = array_values(array_diff($marked, array_keys($found)));

        // people per day now (the overview's Event::people()) and counted again with these waves out – not a difference
        $before = Event::people()->where('created_at', '>=', $since)->where('created_at', '<', $until)->get(['session_id', 'created_at'])
            ->groupBy(fn ($e) => $e->created_at->toDateString())->map(fn ($day) => $day->pluck('session_id')->unique()->count());
        $after = $waves->peopleByDay($since, $until, array_keys($found));
        $wave = Event::whereIn('id', array_keys($found))->get(['session_id', 'created_at'])
            ->groupBy(fn ($e) => $e->created_at->toDateString())->map(fn ($day) => $day->pluck('session_id')->unique()->count());
        $days = [];
        foreach ($before->keys()->merge(array_keys($after))->merge($wave->keys())->unique()->sort() as $day) {
            $days[] = [$day, (int) ($before[$day] ?? 0), (int) ($wave[$day] ?? 0), (int) ($after[$day] ?? 0)];
        }
        $this->table(['day', 'people now', 'visitors of waves', 'people after'], $days);
        $why = array_count_values($found);
        $this->info(sprintf('%d visitors of waves (%d by one browser over many pages, %d from one network), at least %d an hour; %d newly marked, %d unmarked.',
            count($found), $why['browser'] ?? 0, $why['network'] ?? 0, max(2, $min), count($add), count($drop)));
        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }
        foreach (array_chunk($add, 500) as $ids) {
            foreach (Event::whereIn('id', $ids)->get() as $e) {
                $e->forceFill(['meta' => ['bot' => Waves::MARK, 'wave' => $found[$e->id]] + (array) $e->meta])->save();
            }
        }
        foreach (array_chunk($drop, 500) as $ids) {
            foreach (Event::whereIn('id', $ids)->get() as $e) {
                $e->forceFill(['meta' => array_diff_key((array) $e->meta, ['bot' => 1, 'wave' => 1])])->save();
            }
        }

        return self::SUCCESS;
    }
}
