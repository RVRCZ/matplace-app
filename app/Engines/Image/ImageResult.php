<?php

namespace App\Engines\Image;

/** What an image generator hands back: the picture's bytes and type, and what the call cost. */
final class ImageResult
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mime,
        public readonly string $model,
        public readonly int $tokens = 0,
        public readonly int $durationMs = 0,
    ) {}

    public function extension(): string
    {
        return ['image/png' => 'png', 'image/webp' => 'webp'][$this->mime] ?? 'jpg';
    }
}
