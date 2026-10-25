<?php

namespace App\Domain\Calculation;

use App\Engines\Mesh\StlFile;
use App\Models\Calculation;
use App\Models\ModelFile;
use Illuminate\Support\Facades\Storage;

/**
 * A model too big for the slice on the first page (TODO #41, docs/prompts/kalkulace-odhad.md): millions of triangles
 * (thinned before every slice, docs/AL.md §10.11), or a file whose last slice took over a minute. The calculator shows
 * such a model its estimate with a range and no slice; the exact price and time come on the order page, where the
 * farm slices once the customer has made their choices. The same threshold serves the order page (session 2).
 */
final class BigModel
{
    public static function is(ModelFile $f): bool
    {
        if (self::triangles($f) > (int) config('engines.mesh.decimate_above')) {
            return true;
        }
        $limit = (float) config('engines.mesh.big_slice_s', 60);
        if ($limit <= 0 || ! $f->exists) {
            return false;
        }
        $last = Calculation::query()
            ->where('model_file_id', $f->id)
            ->where('status', Calculation::STATUS_DONE)
            ->whereNotNull('slicer')
            ->latest('id')
            ->first(['id', 'timings']);

        return (float) (($last?->timings ?? [])['slice_s'] ?? 0) > $limit;
    }

    /**
     * The triangles of the file: as measured, or, right after the upload when nothing has measured it yet, from the
     * header of a binary STL (a dragon of 1.8 M triangles was calculated in full on 25 Oct 2026 because the upload's
     * answer came before the measuring and said "not big").
     */
    public static function triangles(ModelFile $f): int
    {
        $n = (int) $f->triangles;
        if ($n > 0 || $f->ext !== 'stl') {
            return $n;
        }
        $path = $f->absoluteStlPath() ?? ($f->storage_path ? Storage::disk(ModelFile::DISK)->path($f->storage_path) : null);
        if ($path && is_file($path) && StlFile::isBinary($path)) {
            return StlFile::triangleCount($path);
        }

        return $n;
    }
}
