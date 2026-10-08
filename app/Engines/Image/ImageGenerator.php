<?php

namespace App\Engines\Image;

use App\Engines\Exceptions\EngineException;

/**
 * A picture from a description, for the tools that take a picture (a silhouette for a cutter or a stencil, a line
 * drawing for a stamp, flat colours for a picture in filament). Gemini in production, a drawn fake in tests.
 */
interface ImageGenerator
{
    public const STYLES = ['silhouette', 'line', 'colour'];

    public const SIZES = ['square', 'wide', 'tall'];

    public function available(): bool;

    /**
     * @param  string  $style  silhouette | line | colour (App\Engines\Image\ImageGenerator::STYLES)
     * @param  string  $size  square | wide | tall
     *
     * @throws EngineException
     */
    public function fromText(string $prompt, string $style, string $size, array $context = []): ImageResult;
}
