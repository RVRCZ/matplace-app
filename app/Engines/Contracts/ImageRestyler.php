<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationStatus;

/**
 * A photo redrawn in another style by the generator itself, before a model is made of it (the cartoon pet figurine:
 * the photo of the animal → a picture of a toy-like figure → the model). Asynchronous like the model: start, then poll.
 */
interface ImageRestyler
{
    /** @param  string  $prompt  what the picture is to become, in English */
    public function restyle(string $imagePath, string $prompt): GenerationHandle;

    /** Done → `previewPath` is the new picture, downloaded to a local file the caller owns. */
    public function pollImage(GenerationHandle $handle): GenerationStatus;
}
