<?php

namespace App\Console\Commands;

use App\Domain\Stats\Speed;
use Illuminate\Console\Command;

/** How long the server's work took the last days: percentiles per step, double slicing, caches, the busiest moment. */
class PerfReport extends Command
{
    protected $signature = 'matplace:perf-report {--days=7}';

    protected $description = 'Percentiles of the steps behind calculations and farm orders (queue, repair, slicing…)';

    public const TITLES = [
        'files' => 'Model files (upload → ready)',
        'calculations' => 'Calculations (precise slice)',
        'farm_first' => 'Farm orders, first slice (upload → sliced)',
        'farm_reslice' => 'Farm orders, re-slice (click → sliced)',
    ];

    public function handle(Speed $speed): int
    {
        $s = $speed->summary(max(1, (int) $this->option('days')));
        $this->line("Last {$s['days']} days, seconds.");
        foreach ($s['groups'] as $key => $g) {
            $this->newLine();
            $this->info(self::TITLES[$key]." – {$g['n']} runs, {$g['failed']} failed");
            if (! $g['phases']) {
                continue;
            }
            $this->table(['step', 'n', 'p50', 'p90', 'max'], array_map(fn ($phase, $v) => [$phase, $v['n'], $v['p50'], $v['p90'], $v['max']], array_keys($g['phases']), $g['phases']));
        }
        $this->newLine();
        $this->line("Slicer ran twice (without, then with supports): {$s['double_slices']} of {$s['slices']}");
        $this->line("Calculations served from an earlier one: {$s['calc_cache_hits']}; slices served from the slice cache: {$s['slice_cache_hits']}");
        $this->line("Farm preparation from cache: {$s['prepare_cached']} of {$s['prepared']}");
        $this->line("Waited more than 5 s in the queue: {$s['queued_over_5s']}; most jobs at one moment: {$s['peak']}");
        foreach ($s['slice1_errors'] as $e) {
            $this->line('  first slice without supports said: '.str_replace("\n", ' | ', $e));
        }

        return self::SUCCESS;
    }
}
