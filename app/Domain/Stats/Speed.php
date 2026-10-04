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

    /** @return list<array<string,mixed>> */
    private function timings(Builder $query, $since): array
    {
        return $query->where('updated_at', '>=', $since)->whereNotNull('timings')->latest('id')->limit(20000)->pluck('timings')
            ->map(fn ($t) => is_array($t) ? $t : (array) json_decode((string) $t, true))->values()->all();
    }
}
