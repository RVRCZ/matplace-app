<?php

namespace App\Engines\DTO;

final class GenerationStatus
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $state,
        public readonly ?string $meshPath = null,
        public readonly ?string $previewPath = null,
        public readonly ?string $error = null,
        public readonly int $progress = 0,
    ) {}

    public function isFinal(): bool
    {
        return in_array($this->state, [self::DONE, self::FAILED], true);
    }
}
