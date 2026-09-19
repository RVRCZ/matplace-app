<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;

/** Text or image → mesh. Asynchronous: start returns a handle, poll returns state and eventually a mesh path. */
interface ModelGenerator
{
    public function fromText(string $prompt, GenerationOptions $options): GenerationHandle;

    public function fromImage(string $imagePath, ?string $hint, GenerationOptions $options): GenerationHandle;

    public function poll(GenerationHandle $handle): GenerationStatus;

    /** Rough cost per generation, used for daily quotas and reporting. */
    public function estimatedCostCents(): int;

    public function name(): string;
}
