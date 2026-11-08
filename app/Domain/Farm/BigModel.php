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
 *
 * The copies of the order count (TODO #68, docs/AL.md §11.9): the farm slices one plate of all the copies, so the
 * slice grows with them. Since the copies print as instances of one object (TODO #77, §11.10) Orca slices the piece
 * once and only exports every instance's paths: a copy beyond the first adds a share of the first one's slice
 * (farm.big_model.instance_share; the tree of 8 Nov 2026: 1 piece 31 s, 4 instances 65 s), not a whole slice. An
 * order of several copies of a piece that is small on its own is big when the expected slice of the plate is over
 * engines.mesh.big_slice_s (reason `copies`); a single piece is judged as before. The verdict is made once, when the
 * order is made – a change of the copies later does not flip it.
 */
final class BigModel
{
    /**
     * The verdict with its reason, and how long the farm's slice is expected to take (what the order page tells the
     * customer): the calculation's own slice with a margin for the farm's orientation and supports when one measured
     * it, else by the triangles.
     *
     * @param  Calculation|null  $calculation  the calculation the order comes from, when it comes from the calculator
     * @param  int  $copies  how many pieces the order prints (one plate of them is sliced)
     * @return array{big: bool, reason: ?string, triangles: int, copies: int, slice_s: ?float, estimate_s: int}
     */
    public static function judge(ModelFile $file, ?Calculation $calculation = null, int $copies = 1): array
    {
        $copies = max(1, $copies);
        $reason = Threshold::reason($file, $calculation);
        $sliceS = Threshold::lastSliceSeconds($file, $calculation);
        $triangles = Threshold::triangles($file);
        $limit = Threshold::sliceLimit();
        if ($reason === null && $copies > 1 && $limit > 0 && self::expectedSeconds($triangles, $sliceS, $copies) > $limit) {
            // every piece is small, the plate of all of them is not: the slice would take the minutes of a big model
            $reason = 'copies';
        }

        return ['big' => $reason !== null, 'reason' => $reason, 'triangles' => $triangles, 'copies' => $copies, 'slice_s' => $sliceS, 'estimate_s' => self::estimateSeconds($triangles, $sliceS, $copies)];
    }

    /**
     * Seconds the farm's slice of this model is expected to take, `$copies` pieces on one plate. A measured
     * calculation (one piece sliced without supports, in the calculator's pose) times the copies' factor plus a fifth
     * and a quarter of a minute for the farm's own check, orientation and supports; without one, by the triangles of
     * the piece times the copies' factor (config farm.big_model.seconds_per_million); never under the floor (min_seconds).
     */
    public static function estimateSeconds(int $triangles, ?float $sliceSeconds, int $copies = 1): int
    {
        $floor = (int) config('farm.big_model.min_seconds', 120);

        // whole seconds up, without a float's last bit turning 110 into 111
        return max($floor, (int) ceil(round(self::expectedSeconds($triangles, $sliceSeconds, $copies), 6)));
    }

    /** The expectation itself, without the floor: what the verdict on the copies compares with big_slice_s. */
    public static function expectedSeconds(int $triangles, ?float $sliceSeconds, int $copies = 1): float
    {
        $factor = self::copiesFactor($copies);
        if ($sliceSeconds !== null && $sliceSeconds > 0) {
            return $sliceSeconds * $factor * 1.2 + 15;
        }

        return max(0, $triangles) * $factor / 1e6 * (int) config('farm.big_model.seconds_per_million', 150);
    }

    /**
     * How many slices of one piece a plate of `$copies` instances costs: one for the piece, instance_share of one for
     * every further copy (the paths of every instance are still computed and written; measured 0.37–0.45 on the tree,
     * docs/AL.md §11.10). 1 for a single piece.
     */
    public static function copiesFactor(int $copies): float
    {
        return 1.0 + max(0.0, (float) config('farm.big_model.instance_share', 0.4)) * (max(1, $copies) - 1);
    }

    /** "asi N minut": whole minutes, at least one. */
    public static function minutes(?int $seconds): int
    {
        return max(1, (int) ceil(max(0, (int) $seconds) / 60));
    }
}
