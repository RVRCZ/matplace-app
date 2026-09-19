<?php

namespace App\Engines\Repair;

use App\Engines\Contracts\MeshRepair;
use App\Engines\DTO\MeshReport;
use App\Engines\Exceptions\RepairException;
use App\Engines\Mesh\StlFile;

/** Fallback when Python is not available: geometry statistics only, no topology repair. */
final class PhpStlRepair implements MeshRepair
{
    public function name(): string
    {
        return 'php-stl';
    }

    public function check(string $meshPath): MeshReport
    {
        return StlFile::stats($meshPath);
    }

    public function repair(string $meshPath, string $outPath): MeshReport
    {
        throw new RepairException('Mesh repair needs the Python tool (trimesh); only checks are available.');
    }
}
