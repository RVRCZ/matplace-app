<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\MeshReport;

/** Mesh check and repair. check() never modifies the file; repair() writes a new file and returns its report. */
interface MeshRepair
{
    /** @throws \App\Engines\Exceptions\RepairException */
    public function check(string $meshPath): MeshReport;

    /** @throws \App\Engines\Exceptions\RepairException */
    public function repair(string $meshPath, string $outPath): MeshReport;

    public function name(): string;
}
