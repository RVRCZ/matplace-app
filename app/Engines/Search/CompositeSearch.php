<?php

namespace App\Engines\Search;

use App\Engines\Contracts\ModelSearch;
use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;
use App\Engines\Vision\VisionDescriber;

/**
 * Runs every configured source and merges the results (local catalogue first on ties).
 * byImage = describe the photo (VisionDescriber) → search by the suggested queries.
 */
final class CompositeSearch implements ModelSearch
{
    /** @param  ModelSearch[]  $sources */
    public function __construct(private readonly array $sources, private readonly VisionDescriber $vision) {}

    public function source(): string
    {
        return 'composite';
    }

    public function byText(string $query, SearchOptions $options): SearchResultSet
    {
        $set = new SearchResultSet([], $query, []);
        foreach ($this->sources as $s) {
            $set = $set->merge($s->byText($query, $options));
        }

        return new SearchResultSet(array_slice($set->items, 0, $options->limit * 2), $query, $set->sources);
    }

    public function byImage(string $imagePath, SearchOptions $options): SearchResultSet
    {
        $d = $this->vision->describe($imagePath, $options->locale);
        $set = new SearchResultSet([], $d['query'] ?? '', []);
        foreach (array_slice($d['queries'] ?? [], 0, 3) as $q) {
            $set = $set->merge($this->byText($q, new SearchOptions(limit: (int) ceil($options->limit / 2), locale: $options->locale)));
        }

        return new SearchResultSet(array_slice($set->items, 0, $options->limit * 2), $d['query'] ?? '', $set->sources);
    }

    public function fetch(string $externalId): ?ModelCandidate
    {
        [$source, $id] = array_pad(explode(':', $externalId, 2), 2, '');
        foreach ($this->sources as $s) {
            if ($s->source() === $source) {
                return $s->fetch($id);
            }
        }

        return null;
    }

    /** @return string[] */
    public function sourceNames(): array
    {
        return array_map(fn (ModelSearch $s) => $s->source(), $this->sources);
    }
}
