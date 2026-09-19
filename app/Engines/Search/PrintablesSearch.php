<?php

namespace App\Engines\Search;

use App\Engines\Contracts\ModelSearch;
use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Printables public GraphQL (no key). Files need a Prusa login, so candidates are link-only. */
final class PrintablesSearch implements ModelSearch
{
    private const GQL = 'https://api.printables.com/graphql/';

    private const MEDIA = 'https://media.printables.com/';

    public function source(): string
    {
        return 'printables';
    }

    public function byText(string $query, SearchOptions $options): SearchResultSet
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return new SearchResultSet([], $query, ['printables']);
        }
        $key = 'search:printables:'.sha1(mb_strtolower($query).':'.$options->limit);
        // cache only non-empty answers: a blocked/failed call must not hide results for hours
        $items = Cache::get($key) ?? tap((function () use ($query, $options) {
            try {
                $res = Http::timeout(12)->withHeaders(['User-Agent' => 'matplace-search/2.0'])->post(self::GQL, [
                    'query' => 'query Search($q: String!, $limit: Int, $offset: Int) { searchPrints2(query: $q, limit: $limit, offset: $offset) { items { id slug name image { filePath } license { name } user { publicUsername } } } }',
                    'variables' => ['q' => $query, 'limit' => $options->limit, 'offset' => 0],
                ]);
                if (! $res->ok()) {
                    return [];
                }
                $out = [];
                foreach ($res->json('data.searchPrints2.items') ?? [] as $i => $it) {
                    $out[] = [
                        'id' => (string) $it['id'],
                        'title' => (string) ($it['name'] ?? ''),
                        'preview' => ! empty($it['image']['filePath']) ? self::MEDIA.ltrim($it['image']['filePath'], '/') : null,
                        'url' => 'https://www.printables.com/model/'.$it['id'].'-'.($it['slug'] ?? ''),
                        'license' => $it['license']['name'] ?? null,
                        'author' => $it['user']['publicUsername'] ?? null,
                        'score' => round(1 - $i / max(1, $options->limit), 3),
                    ];
                }

                return $out;
            } catch (\Throwable) {
                return [];
            }
        })(), fn ($v) => $v ? Cache::put($key, $v, now()->addHours(6)) : null);

        return new SearchResultSet(array_map(fn ($r) => new ModelCandidate(
            source: 'printables', externalId: $r['id'], title: $r['title'], previewUrl: $r['preview'], externalUrl: $r['url'],
            license: $r['license'], authorName: $r['author'], score: $r['score'], fileAvailable: false,
        ), $items), $query, ['printables']);
    }

    public function byImage(string $imagePath, SearchOptions $options): SearchResultSet
    {
        return new SearchResultSet([], '', ['printables']);
    }

    public function fetch(string $externalId): ?ModelCandidate
    {
        $r = $this->byText($externalId, new SearchOptions(1));

        return $r->items[0] ?? null;
    }
}
