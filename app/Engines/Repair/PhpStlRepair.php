<?php

namespace App\Engines\Repair;

use App\Engines\Contracts\MeshRepair;
use App\Engines\DTO\MeshReport;
use App\Engines\Exceptions\RepairException;
use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\StlTopology;

/** Fallback when Python is not available: geometry statistics and a closedness check in PHP, no repair. */
final class PhpStlRepair implements MeshRepair
{
    public function name(): string
    {
        return 'php-stl';
    }

    public function check(string $meshPath): MeshReport
    {
        $stats = StlFile::stats($meshPath);
        // closed or not is known for meshes small enough to index in PHP; a bigger one stays "not watertight" (unknown)
        $topology = StlTopology::check($meshPath);
        if ($topology['watertight'] !== true) {
            return $stats;
        }

        return new MeshReport(true, $stats->volumeMm3, $stats->areaMm2, $stats->bbox, $stats->triangles, $stats->shells, $stats->flippedNormals, $stats->issues, $stats->engine);
    }

    public function repair(string $meshPath, string $outPath): MeshReport
    {
        throw new RepairException('Mesh repair needs the Python tool (trimesh); only checks are available.');
    }
}
