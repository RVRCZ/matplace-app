<?php

namespace Tests\Unit;

use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchResultSet;
use PHPUnit\Framework\TestCase;

class SearchResultSetTest extends TestCase
{
    public function test_merge_dedupes_and_sorts_by_score(): void
    {
        $a = new SearchResultSet([
            new ModelCandidate('local', '1', 'A', score: 0.5),
            new ModelCandidate('printables', '9', 'P', score: 0.9),
        ], 'q', ['local', 'printables']);
        $b = new SearchResultSet([
            new ModelCandidate('printables', '9', 'P dup', score: 0.1),
            new ModelCandidate('makerworld', '3', 'M', score: 0.7),
        ], '', ['makerworld']);

        $m = $a->merge($b);
        $this->assertSame(['P', 'M', 'A'], array_map(fn ($c) => $c->title, $m->items));
        $this->assertSame('q', $m->query);
        $this->assertSame(['local', 'printables', 'makerworld'], $m->sources);
    }
}
