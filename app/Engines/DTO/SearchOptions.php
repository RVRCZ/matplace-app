<?php

namespace App\Engines\DTO;

final class SearchOptions
{
    public function __construct(
        public readonly int $limit = 10,
        public readonly string $locale = 'cs',
        public readonly bool $requireFile = false, // only candidates whose file we can obtain automatically
    ) {}
}
