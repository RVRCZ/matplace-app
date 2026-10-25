<?php

namespace App\Domain\Calculation;

use App\Models\Calculation;
use App\Models\ModelFile;

/**
 * A model too big for a slice nobody asked for (TODO #41): millions of triangles (thinned before every slice,
 * docs/AL.md §10.11), or a file whose precise slice on the first page took over a minute. The calculator shows such a
 * model its estimate with a range and no slice (docs/prompts/kalkulace-odhad.md); its farm order waits for the
 * customer's choices and the confirmed colour before it is sliced (App\Domain\Farm\BigModel, docs/AL.md §11). One
 * rule, one place: over config engines.mesh.decimate_above triangles (400 000), or the last measured slice of the
 * file over config engines.mesh.big_slice_s (60 s; 0 switches that half of the rule off).
 */
final class BigModel
{
    /** @param  Calculation|null  $calculation  the calculation at hand (an order made from it): its own slice counts first */
    public static function is(ModelFile $f, ?Calculation $calculation = null): bool
    {
        return self::reason($f, $calculation) !== null;
    }

    /** Why the model is big – `triangles` or `slice_time` – or null for a small one. */
    public static function reason(ModelFile $f, ?Calculation $calculation = null): ?string
    {
        if ((int) $f->triangles > (int) config('engines.mesh.decimate_above')) {
            return 'triangles';
        }
        $limit = self::sliceLimit();
        if ($limit > 0 && ($s = self::lastSliceSeconds($f, $calculation)) !== null && $s > $limit) {
            return 'slice_time';
        }

        return null;
    }

    /** Seconds a precise slice of the first page may take before the model counts as big; 0 = that rule is off. */
    public static function sliceLimit(): float
    {
        return (float) config('engines.mesh.big_slice_s', 60);
    }

    /**
     * How long the calculator's precise slice of this file took (calculations.timings.slice_s): the calculation at hand
     * when it measured one, else the latest finished calculations of the same file that did (one answered from the
     * cache of calculations has no slice of its own and is skipped). Null when nothing was ever sliced.
     */
    public static function lastSliceSeconds(ModelFile $f, ?Calculation $calculation = null): ?float
    {
        $of = fn (?Calculation $c): ?float => is_array($c?->timings) && isset($c->timings['slice_s']) && is_numeric($c->timings['slice_s']) ? (float) $c->timings['slice_s'] : null;
        if (($s = $of($calculation)) !== null) {
            return $s;
        }
        if (! $f->exists) {
            return null;
        }
        foreach (Calculation::query()->where('model_file_id', $f->id)->where('status', Calculation::STATUS_DONE)->whereNotNull('timings')->latest('id')->limit(10)->get(['id', 'timings']) as $c) {
            if (($s = $of($c)) !== null) {
                return $s;
            }
        }

        return null;
    }
}
