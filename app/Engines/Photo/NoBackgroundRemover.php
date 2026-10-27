<?php

namespace App\Engines\Photo;

use App\Engines\Exceptions\EngineException;

/** Neither rembg nor the fake: the photo tool says it is not available here. */
final class NoBackgroundRemover implements BackgroundRemover
{
    public function available(): bool
    {
        return false;
    }

    public function cut(string $source, string $target): array
    {
        throw new EngineException('No background remover is installed (ENGINE_PHOTO).');
    }
}
