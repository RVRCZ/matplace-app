<?php

namespace App\Domain\Farm;

use App\Models\Calculation;
use App\Models\ModelFile;

/**
 * When a model is "big" for the farm (TODO #41, docs/AL.md §11): its order is not sliced the moment it is made but
 * waits for the customer's choices and the confirmed colour, because every slice of such a model takes minutes and
 * every change of a choice would be another one. One rule, one place: over the thinning threshold of triangles
 * (config engines.mesh.decimate_above, 400 000), or the calculator's precise slice of the same file took longer
 * than config farm.big_model.slice_seconds (60 s). Everything else is small and sliced at once, as before.
 */
final class BigModel
{
    /**
     * The verdict with its reason, and how long the farm's slice is expected to take (what the order page tells the
     * customer): the calculation's own slice with a margin for the farm's orientation and supports when one measured
     * it, else by the triangles.
     *
     * @return array{big: bool, reason: ?string, triangles: int, slice_s: ?float, estimate_s: int}
     */
    public static function judge(ModelFile $file, ?Calculation $calculation = null): array
    {
        $triangles = (int) $file->triangles;
        $above = (int) config('engines.mesh.decimate_above');
        $limit = (int) config('farm.big_model.slice_seconds', 60);
        $sliceS = self::lastSliceSeconds($file, $calculation);
        $reason = match (true) {
            $above > 0 && $triangles > $above => 'triangles',
            $limit > 0 && $sliceS !== null && $sliceS > $limit => 'slice_time',
            default => null,
        };

        return ['big' => $reason !== null, 'reason' => $reason, 'triangles' => $triangles, 'slice_s' => $sliceS, 'estimate_s' => self::estimateSeconds($triangles, $sliceS)];
    }

    public static function isBig(ModelFile $file, ?Calculation $calculation = null): bool
    {
        return self::judge($file, $calculation)['big'];
    }

    /**
     * Seconds the farm's slice of this model is expected to take. A measured calculation (sliced without supports,
     * in the calculator's pose) plus a fifth and a quarter of a minute for the farm's own check, orientation and
     * supports; without one, by the triangles (config farm.big_model.seconds_per_million), never under the floor.
     */
    public static function estimateSeconds(int $triangles, ?float $sliceSeconds): int
    {
        $floor = (int) config('farm.big_model.min_seconds', 120);
        if ($sliceSeconds !== null && $sliceSeconds > 0) {
            return max($floor, (int) ceil($sliceSeconds * 1.2 + 15));
        }

        return max($floor, (int) ceil($triangles / 1e6 * (int) config('farm.big_model.seconds_per_million', 150)));
    }

    /** "asi N minut": whole minutes, at least one. */
    public static function minutes(?int $seconds): int
    {
        return max(1, (int) ceil(max(0, (int) $seconds) / 60));
    }

    /**
     * How long the calculator's precise slice of this file took (calculations.timings.slice_s): the calculation the
     * order comes from when it measured one, else the latest finished calculation of the same file that did (one
     * answered from the cache of calculations has no slice of its own). Null when nothing was ever sliced.
     */
    public static function lastSliceSeconds(ModelFile $file, ?Calculation $calculation = null): ?float
    {
        $of = fn (?Calculation $c): ?float => is_array($c?->timings) && isset($c->timings['slice_s']) && is_numeric($c->timings['slice_s']) ? (float) $c->timings['slice_s'] : null;
        if (($s = $of($calculation)) !== null) {
            return $s;
        }
        foreach (Calculation::where('model_file_id', $file->id)->where('status', Calculation::STATUS_DONE)->whereNotNull('timings')->latest('id')->limit(10)->get(['id', 'timings']) as $c) {
            if (($s = $of($c)) !== null) {
                return $s;
            }
        }

        return null;
    }
}
