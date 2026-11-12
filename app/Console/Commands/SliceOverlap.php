<?php

namespace App\Console\Commands;

use App\Domain\Stats\Speed;
use Illuminate\Console\Command;

/**
 * How often two slices really run at the same moment and what it costs them (docs/AL.md §22, part C). Reads the
 * `timings` of calculations and farm orders only, writes nothing: the numbers to decide on ORCA_PARALLEL or on fewer
 * threads per slice with.
 */
class SliceOverlap extends Command
{
    protected $signature = 'matplace:slice-overlap {--days=30} {--heavy=20 : seconds of slicer from which a run counts as heavy} {--json}';

    protected $description = 'How often heavy slices meet on the server and what pace they keep alone and together (read-only)';

    public function handle(Speed $speed): int
    {
        $o = $speed->overlap(max(1, (int) $this->option('days')), max(1, (int) $this->option('heavy')));
        if ($this->option('json')) {
            $this->line(json_encode($o, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->line("Last {$o['days']} days. Runs the slicer worked for (no cache): {$o['slicing']}; heavy (slicer ≥ {$o['heavy_s']} s): {$o['heavy']}, {$o['slicer_s']} s of slicer together.");
        $this->line("Heavy runs that met another slicing job: {$o['met']} (for half their time or more: {$o['met_half']}); shared time {$o['overlap_s']} s. Most slicing jobs at one moment: {$o['peak']}.");
        $pace = fn (?float $v, int $n) => $v === null ? 'no runs with a triangle count' : $v.' s per 100 000 triangles (median of '.$n.')';
        $this->line('Pace of heavy runs alone: '.$pace($o['pace_alone'], $o['pace_alone_n']));
        $this->line('Pace of heavy runs sharing half their time or more: '.$pace($o['pace_met'], $o['pace_met_n']));
        $this->line("Waited for a slicer slot (ORCA_PARALLEL): {$o['slot_wait_s']} s in all. Waited over 5 s in the queue: {$o['queued_over_5s']}, longest {$o['queue_max_s']} s.");
        if ($o['worst']) {
            $this->newLine();
            $this->table(['kind', 'started', 'slicer s', 'other jobs', 'shared s', 'share', 'triangles'], array_map(fn ($w) => array_values($w), $o['worst']));
        }

        return self::SUCCESS;
    }
}
