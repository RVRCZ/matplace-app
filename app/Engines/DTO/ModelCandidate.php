<?php

namespace App\Engines\DTO;

final class ModelCandidate
{
    public function __construct(
        public readonly string $source,
        public readonly string $externalId,
        public readonly string $title,
        public readonly ?string $previewUrl = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $license = null,
        public readonly ?string $authorName = null,
        public readonly ?int $localModelId = null,
        public readonly float $score = 0.0,
        public readonly bool $fileAvailable = false,
        public readonly ?string $origin = null, // where the link really leads (printables, makerworld…); null = the same as source
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
