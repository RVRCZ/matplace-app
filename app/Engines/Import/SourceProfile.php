<?php

namespace App\Engines\Import;

/** A designer's public profile on another site. */
final class SourceProfile
{
    public function __construct(
        public readonly string $id,
        public readonly string $handle,
        public readonly string $name,
        public readonly string $bio,
        public readonly string $url,
    ) {}
}
