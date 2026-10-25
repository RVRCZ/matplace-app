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
 *
 * The thinning of a file is remembered (config decimate_cache, a folder): the same bytes with the same target and
 * limits are answered from there – a copy of the thinned file instead of a minute of Python – when a model is
 * calculated again, sliced again in another colour, or printed after the calculation (the dragon, 24 Oct 2026: 52 s
 * of every slice went to thinning the same file over and over). What could not be thinned is remembered as well.
 *
 * The limits follow the layer the mesh will be sliced at (TODO #41 §6): a quarter of a layer at the 99th percentile
 * and one layer at the worst sampled point (0.2 mm → 0.05 / 0.2, 0.28 → 0.07 / 0.28, 0.12 → 0.03 / 0.12), because a
 * deviation under a layer is what the slicer rounds away anyway. Without a layer the config's fixed limits apply.
 * The limits are part of the cache key, so a coarse quality never gets a thinning made for a fine one or the reverse.
 */
final class MeshDecimator
{
    /** Remembered thinnings older than this are thrown away when a new one is written. */
    private const CACHE_DAYS = 30;

    /** Reasons that will not change on a second run of the same file (a missing library or a crash might). */
    private const FINAL_REASONS = ['deviation', 'shape', 'not_watertight', 'small_enough'];

    public function __construct(private readonly ScriptRunner $python, private readonly array $config) {}

    /** Whether this mesh is above the threshold at all (cheap: the triangle count from the file's header). */
    public function wants(string $path): bool
    {
        return (int) $this->config['decimate_above'] > 0 && StlFile::triangleCount($path) > (int) $this->config['decimate_above'];
    }

    /**
     * The deviation allowed for a mesh sliced at this layer: [the 99th percentile, the worst point] in mm. A quarter
     * of the layer and the layer itself; the config's fixed limits when the layer is not known.
     *
     * @return array{0: float, 1: float}
     */
    public function limits(?float $layerMm): array
    {
        if ($layerMm !== null && $layerMm > 0) {
            return [round($layerMm / 4, 3), round($layerMm, 3)];
        }

        return [(float) $this->config['max_deviation_mm'], (float) ($this->config['max_deviation_peak_mm'] ?? 3 * (float) $this->config['max_deviation_mm'])];
    }

    /**
     * Thins `$path` into `$out`. Returns what happened for the timings – `applied` true with the counts and the
     * deviation, or false with the reason (not watertight, deviation too big, the library missing…) – and never
     * throws: a mesh that cannot be thinned is sliced as it is. `cached` true when the answer came from the folder
     * of remembered thinnings. `$layerMm` is the layer the mesh will be sliced at: the limits follow it (limits()).
     *
     * @return array{applied: bool, before: int, after?: int, deviation_mm?: float, reason?: string, seconds: float, cached?: bool, limits: array{0: float, 1: float}}
     */
    public function decimate(string $path, string $out, ?float $layerMm = null): array
    {
        $before = StlFile::triangleCount($path);
        $start = microtime(true);
        $limits = $this->limits($layerMm);
        $key = $this->cacheKey($path, $limits);
        if ($key !== null && ($known = $this->remembered($key, $out)) !== null) {
            return ['before' => $before] + $known + ['seconds' => round(microtime(true) - $start, 2), 'cached' => true, 'limits' => $limits];
        }
        try {
            $r = $this->python->runScript('decimate_tool.py', [$path, $out, (string) (int) $this->config['decimate_target'], (string) $limits[0], (string) $limits[1]], (int) $this->config['decimate_timeout']);
        } catch (\Throwable $e) {
            Log::warning('Mesh decimation failed, slicing the original', ['file' => basename($path), 'error' => $e->getMessage()]);

            return ['applied' => false, 'before' => $before, 'reason' => 'error', 'seconds' => round(microtime(true) - $start, 2), 'limits' => $limits];
        }
        $seconds = round(microtime(true) - $start, 2);
        if (empty($r['ok']) || ! is_file($out) || filesize($out) < 100) {
            @unlink($out);
            Log::info('Mesh not decimated, slicing the original', ['file' => basename($path), 'reason' => $r['reason'] ?? 'unknown', 'tried' => $r['tried'] ?? null, 'limits' => $limits]);
            $result = ['applied' => false, 'before' => $before, 'reason' => (string) ($r['reason'] ?? 'unknown')];
            if ($key !== null && in_array($result['reason'], self::FINAL_REASONS, true)) {
                $this->remember($key, null, $result);
            }

            return $result + ['seconds' => $seconds, 'limits' => $limits];
        }

        $result = ['applied' => true, 'before' => (int) ($r['before'] ?? $before), 'after' => (int) ($r['after'] ?? 0), 'deviation_mm' => (float) ($r['deviation_mm'] ?? 0), 'peak_mm' => (float) ($r['peak_mm'] ?? 0)];
        if ($key !== null) {
            $this->remember($key, $out, $result);
        }

        return $result + ['seconds' => $seconds, 'limits' => $limits];
    }

    /**
     * The place of this file's remembered thinning: its bytes, the target and the limits; null when nothing is remembered.
     *
     * @param  array{0: float, 1: float}  $limits
     */
    private function cacheKey(string $path, array $limits): ?string
    {
        $dir = (string) ($this->config['decimate_cache'] ?? '');
        if ($dir === '') {
            return null;
        }
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return null;
        }
        $hash = @hash_file('xxh128', $path);
        if (! $hash) {
            return null;
        }

        return $dir.'/'.$hash.'-'.(int) $this->config['decimate_target'].'-'.$limits[0].'-'.$limits[1];
    }

    /** @return array<string, mixed>|null the remembered answer, with the thinned file copied to `$out` when there is one */
    private function remembered(string $key, string $out): ?array
    {
        if (! is_file($key.'.json')) {
            return null;
        }
        $r = json_decode((string) @file_get_contents($key.'.json'), true);
        if (! is_array($r) || ! array_key_exists('applied', $r)) {
            return null;
        }
        if (! $r['applied']) {
            return $r;
        }
        if (! is_file($key.'.stl') || ! @copy($key.'.stl', $out) || filesize($out) < 100) {
            @unlink($out);

            return null;
        }
        @touch($key.'.json');   // used again: stays a while longer

        return $r;
    }

    /** @param  array<string, mixed>  $result */
    private function remember(string $key, ?string $stl, array $result): void
    {
        try {
            if ($stl !== null) {
                $tmp = $key.'.'.getmypid().'.tmp';
                if (! @copy($stl, $tmp) || ! @rename($tmp, $key.'.stl')) {
                    @unlink($tmp);

                    return;
                }
            }
            @file_put_contents($key.'.json', json_encode($result));
            $this->forgetOld(dirname($key));
        } catch (\Throwable $e) {
            Log::info('Mesh decimation could not be remembered', ['error' => $e->getMessage()]);
        }
    }

    private function forgetOld(string $dir): void
    {
        $limit = time() - self::CACHE_DAYS * 86400;
        foreach (glob($dir.'/*.json') ?: [] as $json) {
            if ((int) @filemtime($json) < $limit) {
                @unlink($json);
                @unlink(substr($json, 0, -5).'.stl');
            }
        }
    }
}
