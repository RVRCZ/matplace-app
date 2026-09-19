<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;

/** Finds existing models for a part. One implementation per source; a composite merges them. */
interface ModelSearch
{
    public function byText(string $query, SearchOptions $options): SearchResultSet;

    /** May be implemented as "vision description → byText". */
    public function byImage(string $imagePath, SearchOptions $options): SearchResultSet;

    public function fetch(string $externalId): ?ModelCandidate;

    public function source(): string;
}
