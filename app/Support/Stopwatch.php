<?php

namespace App\Support;

/**
 * Seconds spent in the steps of one piece of work (queue, conversion, slicing…), for the `timings` column of model
 * files, calculations and farm orders. Read back by matplace:perf-report and /admin/stats/speed.
 */
final class Stopwatch
{
    /** @var array<string,mixed> */
    private array $data;

    /** @param  array<string,mixed>  $data  what an earlier run already measured (a job released and run again) */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Runs $fn and adds its duration to $phase (a phase measured twice, e.g. two slices, adds up).
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public function measure(string $phase, callable $fn): mixed
    {
        $start = hrtime(true);
        try {
            return $fn();
        } finally {
            $this->add($phase, (hrtime(true) - $start) / 1e9);
        }
    }

    public function add(string $phase, float $seconds): self
    {
        $this->data[$phase.'_s'] = round((float) ($this->data[$phase.'_s'] ?? 0) + $seconds, 2);

        return $this;
    }

    /** Something that is not a duration: triangles, minutes of print, whether a cache served it. */
    public function note(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /** Seconds from a moment (epoch, as microtime(true) gives it) until now. */
    public function since(string $phase, ?float $epoch): self
    {
        return $epoch === null ? $this : $this->add($phase, max(0.0, microtime(true) - $epoch));
    }

    /** @param  array<string,mixed>  $timings  phases measured elsewhere (the slicer's), merged in */
    public function merge(array $timings, string $prefix = ''): self
    {
        foreach ($timings as $k => $v) {
            $this->data[$prefix.$k] = is_float($v) || is_int($v) ? (str_ends_with($k, '_s') ? round((float) ($this->data[$prefix.$k] ?? 0) + $v, 2) : $v) : $v;
        }

        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
