<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\Exceptions\EngineException;

/**
 * STL as uploaded → STL as it goes to the slicer: units applied, holes closed when possible, turned so that it
 * needs the least support and stands on a stable face, placed on Z = 0. Never touches the input file.
 */
interface PrintPreparer
{
    /**
     * @param  float  $unitScale  multiply coordinates by this to get millimetres (1, 10, 25.4, 1000)
     *
     * @throws EngineException when the file is not a usable mesh at all
     */
    public function prepare(string $stlPath, string $outPath, float $unitScale, Dimensions $bed): PreparedMesh;

    public function name(): string;
}
