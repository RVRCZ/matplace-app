<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;

/**
 * Mesh + parameters → time, material, dimensions.
 * One implementation = one slicer engine; printer/material profiles are selected from SliceParams.
 * Implementations never touch the database and only read/write files they are given.
 */
interface Slicer
{
    /** @throws \App\Engines\Exceptions\SlicerException */
    public function slice(string $meshPath, SliceParams $params): SliceResult;

    /** Fast bounding box without a full slice. */
    public function measure(string $meshPath): Dimensions;

    /** Lower-case extensions the engine accepts directly (no conversion needed). */
    public function supportedFormats(): array;

    public function name(): string;
}
