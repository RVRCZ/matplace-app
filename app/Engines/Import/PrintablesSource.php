<?php

namespace App\Engines\Import;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Printables through its public GraphQL API (no key): a profile with its bio, the list of an author's models and
 * the card of a model with pictures, licence, remix parents and file names.
 *
 * The API sits behind Cloudflare and refuses some data-centre addresses (403): config engines.import.proxy
 * lets the calls go out through a proxy.
 */
final class PrintablesSource implements ModelSource
{
    private const GQL = 'https://api.printables.com/graphql/';

    private const MEDIA = 'https://media.printables.com/';

    private const SITE = 'https://www.printables.com';

    /** @param  array{proxy?: ?string, timeout?: int}  $config */
    public function __construct(private readonly array $config = []) {}

    public function key(): string
    {
        return 'printables';
    }

    public function modelId(string $url): ?string
    {
        return preg_match('~^https?://(?:www\.)?printables\.com/(?:[a-z]{2}/)?model/(\d+)~i', trim($url), $m) ? $m[1] : null;
    }

    public function handle(string $url): ?string
    {
        return preg_match('~^https?://(?:www\.)?printables\.com/(?:[a-z]{2}/)?@([A-Za-z0-9._-]+)~i', trim($url), $m) ? $m[1] : null;
    }

    public function profile(string $handle): ?SourceProfile
    {
        $user = $this->query('query($id: ID!) { user(id: $id) { id handle publicUsername bio } }', ['id' => '@'.ltrim($handle, '@')])['user'] ?? null;
        if (! $user) {
            throw new ImportFailed('not_found');
        }

        return new SourceProfile((string) $user['id'], (string) $user['handle'], (string) ($user['publicUsername'] ?? $user['handle']), (string) ($user['bio'] ?? ''), self::SITE.'/@'.$user['handle']);
    }

    public function models(string $authorId, int $limit): ?array
    {
        $out = [];
        $cursor = null;
        while (count($out) < $limit) {
            $page = $this->query(
                'query($id: ID!, $limit: Int, $cursor: String) { userModels(userId: $id, limit: $limit, cursor: $cursor) { cursor items { id name slug image { filePath } remixParents { id } } } }',
                ['id' => $authorId, 'limit' => min(50, $limit - count($out)), 'cursor' => $cursor],
            )['userModels'] ?? null;
            foreach ($page['items'] ?? [] as $item) {
                $out[] = [
                    'id' => (string) $item['id'],
                    'url' => self::SITE.'/model/'.$item['id'].'-'.($item['slug'] ?? ''),
                    'title' => (string) ($item['name'] ?? ''),
                    'image' => ! empty($item['image']['filePath']) ? self::thumb($item['image']['filePath']) : null,
                    'is_remix' => ! empty($item['remixParents']),
                ];
            }
            $cursor = $page['cursor'] ?? null;
            if (! $cursor || empty($page['items'])) {
                break;
            }
        }

        return $out;
    }

    public function fetch(string $modelId): ImportedModel
    {
        $p = $this->query('query($id: ID!) { print(id: $id) {
            id name slug description summary
            tags { name } image { filePath } images { filePath } license { name }
            user { id handle publicUsername } category { name }
            remixParents { parentPrintId parentPrintName url } stls { name }
        } }', ['id' => $modelId])['print'] ?? null;
        if (! $p || empty($p['name'])) {
            throw new ImportFailed('not_found');
        }
        $images = [];
        foreach (array_merge([$p['image'] ?? null], $p['images'] ?? []) as $image) {
            if (! empty($image['filePath'])) {
                $images[self::MEDIA.ltrim($image['filePath'], '/')] = true;
            }
        }
        $parent = $p['remixParents'][0] ?? null;

        return new ImportedModel(
            source: 'printables',
            id: (string) $p['id'],
            url: self::SITE.'/model/'.$p['id'].'-'.($p['slug'] ?? ''),
            title: trim((string) $p['name']),
            descriptionHtml: (string) (($p['description'] ?? '') ?: ($p['summary'] ?? '')),
            images: array_keys($images),
            tags: array_values(array_filter(array_column($p['tags'] ?? [], 'name'))),
            license: trim((string) ($p['license']['name'] ?? '')),
            isRemix: $parent !== null,
            remixSourceUrl: $parent ? (! empty($parent['parentPrintId']) ? self::SITE.'/model/'.$parent['parentPrintId'] : ($parent['url'] ?? null)) : null,
            authorId: (string) ($p['user']['id'] ?? ''),
            authorName: (string) ($p['user']['publicUsername'] ?? $p['user']['handle'] ?? ''),
            files: array_values(array_filter(array_column($p['stls'] ?? [], 'name'))),
            category: $p['category']['name'] ?? null,
        );
    }

    /** The small version Printables keeps of every picture (a few kB instead of the full photo): for lists. */
    private static function thumb(string $filePath): string
    {
        $info = pathinfo(ltrim($filePath, '/'));

        return self::MEDIA.$info['dirname'].'/thumbs/cover/320x240/'.strtolower($info['extension'] ?? 'jpg').'/'.$info['filename'].'.webp';
    }

    /** @return array<string, mixed> the "data" object of the answer */
    private function query(string $query, array $variables): array
    {
        try {
            $res = $this->http()->post(self::GQL, ['query' => $query, 'variables' => $variables]);
        } catch (\Throwable $e) {
            throw new ImportFailed('unreadable', $e->getMessage());
        }
        if ($res->status() === 403 || $res->status() === 429) {
            throw new ImportFailed('blocked', 'HTTP '.$res->status());
        }
        if (! $res->ok() || ! is_array($res->json('data'))) {
            throw new ImportFailed('unreadable', 'HTTP '.$res->status().' '.mb_substr((string) $res->body(), 0, 200));
        }

        return (array) $res->json('data');
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout((int) ($this->config['timeout'] ?? 20))->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
            'Origin' => self::SITE, 'Accept' => 'application/json',
        ]);

        return ! empty($this->config['proxy']) ? $request->withOptions(['proxy' => $this->config['proxy']]) : $request;
    }
}
