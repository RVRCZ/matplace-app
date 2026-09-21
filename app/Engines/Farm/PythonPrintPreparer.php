<?php

namespace App\Engines\Farm;

use App\Engines\Contracts\PrintPreparer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;

/** engines/python/farm_tool.py: repair (trimesh, pymeshfix), automatic orientation, placement. */
final class PythonPrintPreparer implements PrintPreparer
{
    public function __construct(private readonly PythonTool $python, private readonly float $overhangDeg = 40.0) {}

    public function name(): string
    {
        return 'farm_tool';
    }

    public function prepare(string $stlPath, string $outPath, float $unitScale, Dimensions $bed): PreparedMesh
    {
        $r = $this->python->runScript('farm_tool.py', [
            'prepare', $stlPath, $outPath, (string) $unitScale, (string) $bed->x, (string) $bed->y, (string) $bed->z, (string) $this->overhangDeg,
        ], 300);
        if (empty($r['ok'])) {
            throw new EngineException('farm_tool prepare failed: '.($r['error'] ?? 'unknown'));
        }

        return new PreparedMesh(
            path: $outPath,
            watertight: (bool) $r['watertight'],
            wasWatertight: (bool) $r['was_watertight'],
            repaired: (bool) $r['repaired'],
            openEdges: (int) $r['open_edges'],
            bbox: Dimensions::fromArray($r['bbox']),
            volumeMm3: (float) $r['volume_mm3'],
            areaMm2: (float) $r['area_mm2'],
            triangles: (int) $r['triangles'],
            shells: (int) $r['shells'],
            orientation: (array) $r['orientation'] + ['repair_method' => $r['repair_method'] ?? null],
            engine: $this->name(),
        );
    }
}
