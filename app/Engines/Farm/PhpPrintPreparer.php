<?php

namespace App\Engines\Farm;

use App\Engines\Contracts\PrintPreparer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\StlTopology;

/**
 * Fallback without Python (and what the tests run): units and a real closed-surface check, but no repair and no
 * re-orientation: the model is printed the way it was uploaded.
 */
final class PhpPrintPreparer implements PrintPreparer
{
    public function name(): string
    {
        return 'php-stl';
    }

    public function prepare(string $stlPath, string $outPath, float $unitScale, Dimensions $bed): PreparedMesh
    {
        StlFile::scale($stlPath, $outPath, $unitScale);
        $stats = StlFile::stats($outPath);
        $topology = StlTopology::check($outPath);

        return new PreparedMesh(
            path: $outPath,
            watertight: $topology['watertight'],
            wasWatertight: (bool) $topology['watertight'],
            repaired: false,
            openEdges: $topology['open_edges'] + $topology['non_manifold_edges'],
            bbox: $stats->bbox,
            volumeMm3: $stats->volumeMm3,
            areaMm2: $stats->areaMm2,
            triangles: $stats->triangles,
            shells: 1,
            orientation: ['method' => 'none', 'changed' => false],
            engine: $this->name(),
        );
    }
}
