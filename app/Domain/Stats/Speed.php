<?php

namespace App\Domain\Stats;

use App\Models\Calculation;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use Illuminate\Database\Eloquent\Builder;

/**
 * How long customers wait for the server's work, from the `timings` column the jobs write (App\Support\Stopwatch):
 * percentiles per step for files, calculations and farm orders (first slice and re-slice apart), how often the
 * slicer ran twice, how often a cache served, and how many jobs ran at the same time.
 * Read by `matplace:perf-report` and /admin/stats/speed.
 */
final class Speed
{
    /** The steps worth a row, in the order the work goes through them; the rest of the keys are notes. */
    public const PHASES = [
        'files' => ['queue', 'convert', 'analyse'],
        'calculations' => ['wait', 'queue', 'mesh', 'slice1', 'slice2', 'parse', 'price', 'total'],
        'farm' => ['wait', 'queue', 'prepare', 'layout', 'mesh', 'slice1', 'slice2', 'parse', 'rest_slice', 'post', 'price', 'total', 'to_sliced'],
    ];

    /** @return array<string,mixed> */
    public function summary(int $days = 7): array
    {
        $since = now()->subDays($days);
        $files = $this->timings(ModelFile::query(), $since);
        $calcs = $this->timings(Calculation::query(), $since);
        $orders = $this->timings(FarmOrder::query(), $since);
        // what the customer waits for, from the upload (first slice) or from the click (re-slice) to "sliced"
        $orders = array_map(fn (array $t) => $t + ['to_sliced_s' => round((float) ($t['wait_s'] ?? $t['queue_s'] ?? 0) + (float) ($t['total_s'] ?? 0), 2)], $orders);
        $first = array_values(array_filter($orders, fn (array $t) => empty($t['reslice'])));
        $again = array_values(array_filter($orders, fn (array $t) => ! empty($t['reslice'])));
        $sliced = array_values(array_filter($calcs, fn (array $t) => empty($t['cache'])));

        return [
            'days' => $days,
            'groups' => [
                'files' => $this->group($files, self::PHASES['files']),
                'calculations' => $this->group($sliced, self::PHASES['calculations']),
                'farm_first' => $this->group($first, self::PHASES['farm']),
                'farm_reslice' => $this->group($again, self::PHASES['farm']),
            ],
            'calc_cache_hits' => count($calcs) - count($sliced),
            'slice_cache_hits' => count(array_filter([...$sliced, ...$orders], fn (array $t) => ! empty($t['slice_cached']))),
            'double_slices' => count(array_filter([...$sliced, ...$orders], fn (array $t) => ! empty($t['double']))),
            'slices' => count($sliced) + count($orders),
            'prepare_cached' => count(array_filter($orders, fn (array $t) => ! empty($t['prepare_cached']))),
            'prepared' => count(array_filter($orders, fn (array $t) => array_key_exists('prepare_cached', $t))),
            'queued_over_5s' => count(array_filter([...$files, ...$sliced, ...$orders], fn (array $t) => (float) ($t['queue_s'] ?? 0) > 5)),
            'peak' => self::peak([...$files, ...$sliced, ...$orders]),
            'slice1_errors' => array_slice(array_values(array_filter(array_map(fn (array $t) => $t['slice1_error'] ?? null, [...$sliced, ...$orders]))), 0, 5),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  list<string>  $phases
     * @return array{n:int, failed:int, phases: array<string, array{n:int, p50:float, p90:float, max:float}>}
     */
    public function group(array $rows, array $phases): array
    {
        $out = [];
        foreach ($phases as $phase) {
            $values = array_values(array_filter(array_map(fn (array $t) => isset($t[$phase.'_s']) ? (float) $t[$phase.'_s'] : null, $rows), fn ($v) => $v !== null));
            if ($values) {
                $out[$phase] = ['n' => count($values), 'p50' => self::percentile($values, 50), 'p90' => self::percentile($values, 90), 'max' => max($values)];
            }
        }

        return ['n' => count($rows), 'failed' => count(array_filter($rows, fn (array $t) => ! empty($t['failed']))), 'phases' => $out];
    }

    /** Nearest-rank percentile. */
    public static function percentile(array $values, int $p): float
    {
        sort($values);

        return round((float) $values[max(0, (int) ceil($p / 100 * count($values)) - 1)], 2);
    }

    /**
     * The most jobs that ran at one moment, from their start and end.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    public static function peak(array $rows): int
    {
        $edges = [];
        foreach ($rows as $t) {
            if (isset($t['started'], $t['finished'])) {
                $edges[] = [(float) $t['started'], 1];
                $edges[] = [(float) $t['finished'], -1];
            }
        }
        // an end before a start at the same moment: back-to-back jobs did not overlap
        usort($edges, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $now = $max = 0;
        foreach ($edges as [, $d]) {
            $now += $d;
            $max = max($max, $now);
        }

        return $max;
    }

    /**
     * How often slices really meet and what it costs them (docs/AL.md §22, part C), from the timings alone. A run "slices"
     * when the slicer process ran for it (slice1_s + slice2_s, not served from a cache); it is "heavy" from `$heavy`
     * seconds of that on. For every heavy run: with how many other slicing runs its job shared the time, and for how
     * long. The cost is read off the pace – seconds of slicer per 100 000 triangles sliced – of the heavy runs that ran
     * alone against those that shared half their time or more; it is only as good as the runs are alike, so the number
     * of runs behind each pace is given with it.
     *
     * @return array{days:int, heavy_s:int, slicing:int, heavy:int, met:int, met_half:int, overlap_s:float, slicer_s:float,
     *     peak:int, pace_alone:?float, pace_alone_n:int, pace_met:?float, pace_met_n:int, slot_wait_s:float,
     *     queued_over_5s:int, queue_max_s:float, worst: list<array<string,mixed>>}
     */
    public function overlap(int $days = 30, int $heavy = 20): array
    {
        $since = now()->subDays($days);

        return self::overlapOf(['calc' => $this->timings(Calculation::query(), $since), 'farm' => $this->timings(FarmOrder::query(), $since)], $days, $heavy);
    }

    /**
     * overlap() over timings already read: `$timings` = kind => the rows' timings.
     *
     * @param  array<string, list<array<string,mixed>>>  $timings
     * @return array<string,mixed>
     */
    public static function overlapOf(array $timings, int $days, int $heavy): array
    {
        $rows = [];
        foreach ($timings as $kind => $list) {
            foreach ($list as $t) {
                $slicer = (float) ($t['slice1_s'] ?? 0) + (float) ($t['slice2_s'] ?? 0) + (float) ($t['rest_slice1_s'] ?? 0) + (float) ($t['rest_slice2_s'] ?? 0);
                if (! isset($t['started'], $t['finished']) || $slicer <= 0 || ! empty($t['slice_cached']) || ! empty($t['cache'])) {
                    continue;
                }
                $rows[] = ['kind' => $kind, 'from' => (float) $t['started'], 'to' => (float) $t['finished'], 'slicer_s' => $slicer,
                    'triangles' => (int) ($t['triangles_sliced'] ?? $t['triangles'] ?? 0), 'queue_s' => (float) ($t['queue_s'] ?? 0), 'wait_s' => (float) ($t['orca_wait_s'] ?? 0)];
            }
        }
        usort($rows, fn ($a, $b) => $a['from'] <=> $b['from']);
        $out = ['days' => $days, 'heavy_s' => $heavy, 'slicing' => count($rows), 'heavy' => 0, 'met' => 0, 'met_half' => 0, 'overlap_s' => 0.0, 'slicer_s' => 0.0,
            'peak' => self::peak(array_map(fn ($r) => ['started' => $r['from'], 'finished' => $r['to']], $rows)),
            'slot_wait_s' => round(array_sum(array_column($rows, 'wait_s')), 1),
            'queued_over_5s' => count(array_filter($rows, fn ($r) => $r['queue_s'] > 5)), 'queue_max_s' => $rows ? round(max(array_column($rows, 'queue_s')), 1) : 0.0, 'worst' => []];
        $alone = $shared = [];
        foreach ($rows as $i => $r) {
            if ($r['slicer_s'] < $heavy) {
                continue;
            }
            $out['heavy']++;
            $out['slicer_s'] += $r['slicer_s'];
            // the stretches of this job's time another slicing job was running too, merged
            $cuts = [];
            $others = 0;
            foreach ($rows as $j => $o) {
                if ($j !== $i && $o['from'] < $r['to'] && $o['to'] > $r['from']) {
                    $cuts[] = [max($o['from'], $r['from']), min($o['to'], $r['to'])];
                    $others++;
                }
            }
            usort($cuts, fn ($a, $b) => $a[0] <=> $b[0]);
            $met = 0.0;
            $end = null;
            foreach ($cuts as [$a, $b]) {
                if ($end === null || $a > $end) {
                    $met += $b - $a;
                    $end = $b;
                } elseif ($b > $end) {
                    $met += $b - $end;
                    $end = $b;
                }
            }
            $share = $r['to'] > $r['from'] ? $met / ($r['to'] - $r['from']) : 0.0;
            if ($others > 0) {
                $out['met']++;
                $out['overlap_s'] += $met;
                $out['worst'][] = ['kind' => $r['kind'], 'at' => date('Y-m-d H:i', (int) $r['from']), 'slicer_s' => round($r['slicer_s'], 1), 'others' => $others, 'shared_s' => round($met, 1), 'share' => round($share, 2), 'triangles' => $r['triangles']];
            }
            if ($share >= 0.5) {
                $out['met_half']++;
            }
            if ($r['triangles'] > 0) {
                $pace = $r['slicer_s'] / ($r['triangles'] / 100000);
                if ($others === 0) {
                    $alone[] = $pace;
                } elseif ($share >= 0.5) {
                    $shared[] = $pace;
                }
            }
        }
        usort($out['worst'], fn ($a, $b) => $b['shared_s'] <=> $a['shared_s']);
        $out['worst'] = array_slice($out['worst'], 0, 10);
        $out['overlap_s'] = round($out['overlap_s'], 1);
        $out['slicer_s'] = round($out['slicer_s'], 1);

        return $out + ['pace_alone' => $alone ? self::percentile($alone, 50) : null, 'pace_alone_n' => count($alone), 'pace_met' => $shared ? self::percentile($shared, 50) : null, 'pace_met_n' => count($shared)];
    }

    /** @return list<array<string,mixed>> */
    private function timings(Builder $query, $since): array
    {
        return $query->where('updated_at', '>=', $since)->whereNotNull('timings')->latest('id')->limit(20000)->pluck('timings')
            ->map(fn ($t) => is_array($t) ? $t : (array) json_decode((string) $t, true))->values()->all();
    }
}
