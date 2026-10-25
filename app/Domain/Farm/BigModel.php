<?php

namespace App\Domain\Farm;

use App\Domain\Calculation\BigModel as Threshold;
use App\Models\Calculation;
use App\Models\ModelFile;

/**
 * What a big model means for the farm (TODO #41, docs/AL.md §11): its order is not sliced the moment it is made but
 * waits for the customer's choices and the confirmed colour, because every slice of such a model takes minutes and
 * every change of a choice would be another one. Whether a model is big is decided in one place for the first page
 * and the farm alike, App\Domain\Calculation\BigModel; this class adds what the order page needs – the reason, and
 * how long the farm's slice is expected to take.
 */
final class BigModel
{
    /**
     * The verdict with its reason, and how long the farm's slice is expected to take (what the order page tells the
     * customer): the calculation's own slice with a margin for the farm's orientation and supports when one measured
     * it, else by the triangles.
     *
     * @param  Calculation|null  $calculation  the calculation the order comes from, when it comes from the calculator
     * @return array{big: bool, reason: ?string, triangles: int, slice_s: ?float, estimate_s: int}
     */
    public static function judge(ModelFile $file, ?Calculation $calculation = null): array
    {
        $reason = Threshold::reason($file, $calculation);
        $sliceS = Threshold::lastSliceSeconds($file, $calculation);

        return ['big' => $reason !== null, 'reason' => $reason, 'triangles' => (int) $file->triangles, 'slice_s' => $sliceS, 'estimate_s' => self::estimateSeconds((int) $file->triangles, $sliceS)];
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
}
