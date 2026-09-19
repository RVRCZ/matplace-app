<?php

namespace App\Engines\DTO;

final class SearchResultSet
{
    /** @param  ModelCandidate[]  $items */
    public function __construct(
        public readonly array $items,
        public readonly string $query = '',
        public readonly array $sources = [],
    ) {}

    /** Merge two result sets, de-duplicated by source + external id, best score first. */
    public function merge(self $other): self
    {
        $seen = [];
        $items = [];
        foreach ([...$this->items, ...$other->items] as $c) {
            $key = $c->source.':'.$c->externalId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = $c;
        }
        usort($items, fn ($a, $b) => $b->score <=> $a->score);

        return new self($items, $this->query ?: $other->query, array_values(array_unique([...$this->sources, ...$other->sources])));
    }
}
