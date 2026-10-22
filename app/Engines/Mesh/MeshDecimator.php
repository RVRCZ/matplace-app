<?php

namespace App\Engines\Mesh;

use App\Engines\Repair\ScriptRunner;
use Illuminate\Support\Facades\Log;

/**
 * A very fine mesh thinned before it is sliced (docs/AL.md §10.11): above the threshold of config engines.mesh the
 * triangles go down to about the target by quadric edge collapse (engines/python/decimate_tool.py), and the thinned
 * copy is kept only when its worst deviation from the original stays within the limit and it is still watertight. The
 * slicer cannot tell the difference at a 0.2 mm layer, and a scanned dragon of 1.8 M triangles slices in a fraction of
 * the time. The original file is never touched: it is what the customer sees and downloads.
 */
final class MeshDecimator
{
    public function __construct(private readonly ScriptRunner $python, private readonly array $config) {}

    /** Whether this mesh is above the threshold at all (cheap: the triangle count from the file's header). */
    public function wants(string $path): bool
    {
        return (int) $this->config['decimate_above'] > 0 && StlFile::triangleCount($path) > (int) $this->config['decimate_above'];
    }

    /**
     * Thins `$path` into `$out`. Returns what happened for the timings – `applied` true with the counts and the
     * deviation, or false with the reason (not watertight, deviation too big, the library missing…) – and never
     * throws: a mesh that cannot be thinned is sliced as it is.
     *
     * @return array{applied: bool, before: int, after?: int, deviation_mm?: float, reason?: string, seconds: float}
     */
    public function decimate(string $path, string $out): array
    {
        $before = StlFile::triangleCount($path);
        $start = microtime(true);
        try {
            $r = $this->python->runScript('decimate_tool.py', [$path, $out, (string) (int) $this->config['decimate_target'], (string) (float) $this->config['max_deviation_mm'], (string) (float) ($this->config['max_deviation_peak_mm'] ?? 3 * (float) $this->config['max_deviation_mm'])], (int) $this->config['decimate_timeout']);
        } catch (\Throwable $e) {
            Log::warning('Mesh decimation failed, slicing the original', ['file' => basename($path), 'error' => $e->getMessage()]);

            return ['applied' => false, 'before' => $before, 'reason' => 'error', 'seconds' => round(microtime(true) - $start, 2)];
        }
        $seconds = round(microtime(true) - $start, 2);
        if (empty($r['ok']) || ! is_file($out) || filesize($out) < 100) {
            @unlink($out);
            Log::info('Mesh not decimated, slicing the original', ['file' => basename($path), 'reason' => $r['reason'] ?? 'unknown', 'tried' => $r['tried'] ?? null]);

            return ['applied' => false, 'before' => $before, 'reason' => (string) ($r['reason'] ?? 'unknown'), 'seconds' => $seconds];
        }

        return ['applied' => true, 'before' => (int) ($r['before'] ?? $before), 'after' => (int) ($r['after'] ?? 0), 'deviation_mm' => (float) ($r['deviation_mm'] ?? 0), 'peak_mm' => (float) ($r['peak_mm'] ?? 0), 'seconds' => $seconds];
    }
}
