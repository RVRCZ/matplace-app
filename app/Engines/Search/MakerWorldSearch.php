<?php

namespace App\Engines\Search;

use App\Engines\Contracts\ModelSearch;
use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * MakerWorld public search. Often behind a Cloudflare challenge for non-browser clients; when that happens
 * the engine returns nothing and the composite falls back to the local catalogue (1 900 MakerWorld models indexed).
 */
final class MakerWorldSearch implements ModelSearch
{
    private const API = 'https://makerworld.com/api/v1/design-service/search';

    public function source(): string
    {
        return 'makerworld';
    }

    public function byText(string $query, SearchOptions $options): SearchResultSet
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return new SearchResultSet([], $query, ['makerworld']);
        }
        $key = 'search:makerworld:'.sha1(mb_strtolower($query).':'.$options->limit);
        // cache only non-empty answers: a blocked/failed call must not hide results for hours
        $items = Cache::get($key) ?? tap((function () use ($query, $options) {
            try {
                $res = Http::timeout(10)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                    'Accept' => 'application/json',
                ])->get(self::API, ['keyword' => $query, 'limit' => $options->limit, 'offset' => 0]);
                if (! $res->ok() || ! str_contains((string) $res->header('Content-Type'), 'json')) {
                    return [];
                }
                $hits = $res->json('hits') ?? $res->json('data.hits') ?? $res->json('designs') ?? [];
                $out = [];
                foreach (array_slice($hits, 0, $options->limit) as $i => $h) {
                    $id = (string) ($h['id'] ?? $h['designId'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $out[] = [
                        'id' => $id,
                        'title' => (string) ($h['title'] ?? $h['name'] ?? ''),
                        'preview' => $h['cover'] ?? $h['coverUrl'] ?? null,
                        'url' => 'https://makerworld.com/en/models/'.$id,
                        'license' => $h['license'] ?? null,
                        'author' => $h['designCreator']['name'] ?? $h['creator']['name'] ?? null,
                        'score' => round(0.9 - $i / max(1, $options->limit), 3),
                    ];
                }

                return $out;
            } catch (\Throwable) {
                return [];
            }
        })(), fn ($v) => $v ? Cache::put($key, $v, now()->addHours(6)) : null);

        return new SearchResultSet(array_map(fn ($r) => new ModelCandidate(
            source: 'makerworld', externalId: $r['id'], title: $r['title'], previewUrl: $r['preview'], externalUrl: $r['url'],
            license: $r['license'], authorName: $r['author'], score: $r['score'], fileAvailable: false,
        ), $items), $query, ['makerworld']);
    }

    public function byImage(string $imagePath, SearchOptions $options): SearchResultSet
    {
        return new SearchResultSet([], '', ['makerworld']);
    }

    public function fetch(string $externalId): ?ModelCandidate
    {
        return null;
    }
}
