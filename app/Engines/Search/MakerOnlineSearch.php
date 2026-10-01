<?php

namespace App\Engines\Search;

use App\Engines\Contracts\ModelSearch;
use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * MakerOnline (Creality's model site): its public search endpoint, taken over from the old admin's import.
 * Link-only, like the other outside sources; used by the admin when filling the inspiration catalogue, not by the
 * search on the home page.
 */
final class MakerOnlineSearch implements ModelSearch
{
    private const URL = 'https://makeronline.com/api/search/model';

    public function source(): string
    {
        return 'makeronline';
    }

    public function byText(string $query, SearchOptions $options): SearchResultSet
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return new SearchResultSet([], $query, ['makeronline']);
        }
        $key = 'search:makeronline:'.sha1(mb_strtolower($query).':'.$options->limit);
        // cache only non-empty answers: a blocked or failed call must not hide results for hours
        $items = Cache::get($key) ?? tap((function () use ($query, $options) {
            try {
                $res = Http::timeout(15)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; matplace-search/2.0)', 'Accept' => 'application/json', 'Referer' => 'https://makeronline.com/',
                ])->post(self::URL, ['keyword' => $query, 'category_id' => 0, 'page' => 1, 'page_size' => min(50, $options->limit), 'print_type' => 0, 'pure_search' => 0]);
                if (! $res->ok()) {
                    return [];
                }
                $out = [];
                foreach ((array) $res->json('data.data') as $i => $it) {
                    $url = (string) ($it['target_url'] ?? '');
                    $title = trim((string) ($it['title'] ?? ''));
                    if ($title === '' || ! preg_match('#^https://#', $url)) {
                        continue;
                    }
                    $out[] = [
                        'id' => (string) ($it['id'] ?? sha1($url)), 'title' => $title, 'url' => $url,
                        'preview' => preg_match('#^https://#', (string) ($it['mold_image'] ?? '')) ? (string) $it['mold_image'] : null,
                        'author' => isset($it['user_name']) ? (string) $it['user_name'] : null,
                        'score' => round(1 - $i / max(1, $options->limit), 3),
                    ];
                }

                return $out;
            } catch (\Throwable) {
                return [];
            }
        })(), fn ($v) => $v ? Cache::put($key, $v, now()->addHours(6)) : null);

        return new SearchResultSet(array_map(fn ($r) => new ModelCandidate(
            source: 'makeronline', externalId: $r['id'], title: $r['title'], previewUrl: $r['preview'], externalUrl: $r['url'],
            license: null, authorName: $r['author'], score: $r['score'], fileAvailable: false,
        ), $items), $query, ['makeronline']);
    }

    public function byImage(string $imagePath, SearchOptions $options): SearchResultSet
    {
        return new SearchResultSet([], '', ['makeronline']);
    }

    public function fetch(string $externalId): ?ModelCandidate
    {
        return null;   // the site has no public address for one model by id; the admin imports from search results
    }
}
