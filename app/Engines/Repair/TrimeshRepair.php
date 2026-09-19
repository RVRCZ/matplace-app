<?php

namespace App\Engines\Repair;

use App\Engines\Contracts\MeshRepair;
use App\Engines\DTO\MeshReport;
use App\Engines\Exceptions\RepairException;

/** Mesh check and repair through trimesh (+ pymeshfix when installed). */
final class TrimeshRepair implements MeshRepair
{
    public function __construct(private readonly PythonTool $python) {}

    public function name(): string
    {
        return 'trimesh';
    }

    public function check(string $meshPath): MeshReport
    {
        $out = $this->python->run(['check', $meshPath]);
        if (empty($out['ok'])) {
            throw new RepairException('Mesh check failed: '.($out['error'] ?? 'unknown'));
        }

        return MeshReport::fromArray($out + ['engine' => $this->name()]);
    }

    public function repair(string $meshPath, string $outPath): MeshReport
    {
        $out = $this->python->run(['repair', $meshPath, $outPath]);
        if (empty($out['ok'])) {
            throw new RepairException('Mesh repair failed: '.($out['error'] ?? 'unknown'));
        }

        return MeshReport::fromArray($out + ['engine' => $this->name()]);
    }
}
