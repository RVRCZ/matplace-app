<?php

namespace App\Engines\Photo;

use App\Engines\Exceptions\EngineException;

/**
 * Takes the background off a product photo: the thing stays, everything round it becomes transparent (an RGBA PNG
 * cropped to the thing). rembg (u2net) on the server, a drawn fake in tests, nothing when neither is installed.
 */
interface BackgroundRemover
{
    public function available(): bool;

    /**
     * @return array{width: int, height: int, coverage: float} the cut-out's size and how much of it is the thing (0–1)
     *
     * @throws EngineException
     */
    public function cut(string $source, string $target): array;
}
