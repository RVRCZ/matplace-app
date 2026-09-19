<?php

namespace App\Engines\Generator;

use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Exceptions\GenerationException;

/** Placeholder until a provider is chosen (step 3). Every call fails loudly with a clear message. */
final class NullGenerator implements ModelGenerator
{
    public function name(): string
    {
        return 'null';
    }

    public function fromText(string $prompt, GenerationOptions $options): GenerationHandle
    {
        throw new GenerationException('Model generation is not configured (ENGINE_GENERATOR=null).');
    }

    public function fromImage(string $imagePath, ?string $hint, GenerationOptions $options): GenerationHandle
    {
        throw new GenerationException('Model generation is not configured (ENGINE_GENERATOR=null).');
    }

    public function poll(GenerationHandle $handle): GenerationStatus
    {
        return new GenerationStatus(GenerationStatus::FAILED, error: 'not configured');
    }

    public function estimatedCostCents(): int
    {
        return 0;
    }
}
