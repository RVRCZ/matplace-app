<?php

namespace App\Engines\Import;

use Illuminate\Support\Facades\Http;

/**
 * MakerWorld (Bambu Lab). The only thing it shows without a browser session is the card of one model
 * (/api/v1/design-service/design/{id}); profiles and lists of an author's models are behind a Cloudflare
 * challenge (checked 1 Oct 2026). So there is no profile and no list here: a designer proves the account by
 * a token in the description of one of their models and pastes the addresses of the models to import.
 */
final class MakerWorldSource implements ModelSource
{
    private const API = 'https://makerworld.com/api/v1/design-service/design/';

    private const SITE = 'https://makerworld.com';

    /** @param  array{proxy?: ?string, timeout?: int}  $config */
    public function __construct(private readonly array $config = []) {}

    public function key(): string
    {
        return 'makerworld';
    }

    public function modelId(string $url): ?string
    {
        return preg_match('~^https?://(?:www\.)?makerworld\.com/(?:[a-z]{2}(?:-[A-Za-z]{2})?/)?models/(\d+)~i', trim($url), $m) ? $m[1] : null;
    }

    public function handle(string $url): ?string
    {
        return preg_match('~^https?://(?:www\.)?makerworld\.com/(?:[a-z]{2}(?:-[A-Za-z]{2})?/)?@([A-Za-z0-9._-]+)~i', trim($url), $m) ? $m[1] : null;
    }

    public function profile(string $handle): ?SourceProfile
    {
        return null;
    }

    public function models(string $authorId, int $limit): ?array
    {
        return null;
    }

    public function fetch(string $modelId): ImportedModel
    {
        try {
            $request = Http::timeout((int) ($this->config['timeout'] ?? 20))->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
                'Accept' => 'application/json', 'Accept-Language' => 'en', 'Referer' => self::SITE.'/',
            ]);
            $res = (! empty($this->config['proxy']) ? $request->withOptions(['proxy' => $this->config['proxy']]) : $request)->get(self::API.$modelId);
        } catch (\Throwable $e) {
            throw new ImportFailed('unreadable', $e->getMessage());
        }
        if ($res->status() === 403 || $res->status() === 429) {
            throw new ImportFailed('blocked', 'HTTP '.$res->status());
        }
        if ($res->status() === 404) {
            throw new ImportFailed('not_found');
        }
        $json = $res->json();
        $d = is_array($json) ? ($json['data']['design'] ?? $json['data'] ?? $json) : null;
        if (! $res->ok() || ! is_array($d) || (empty($d['title']) && empty($d['name']))) {
            throw new ImportFailed('unreadable', 'HTTP '.$res->status());
        }

        $images = [];
        foreach ([$d['coverUrl'] ?? '', $d['coverLandscape'] ?? '', $d['coverPortrait'] ?? ''] as $cover) {
            if ($cover) {
                $images[$cover] = true;
                break;
            }
        }
        $files = [];
        foreach ($d['instances'] ?? [] as $instance) {
            foreach ($instance['pictures'] ?? [] as $picture) {
                if (! empty($picture['url'])) {
                    $images[$picture['url']] = true;
                }
            }
            if (! empty($instance['title'])) {
                $files[] = (string) $instance['title'];
            }
        }
        $creator = (array) ($d['designCreator'] ?? []);
        $original = $d['originals'][0] ?? null;
        $license = strtoupper(trim((string) ($d['license'] ?? '')));

        return new ImportedModel(
            source: 'makerworld',
            id: (string) ($d['id'] ?? $modelId),
            url: self::SITE.'/en/models/'.($d['id'] ?? $modelId).(! empty($d['slug']) ? '-'.$d['slug'] : ''),
            title: trim((string) ($d['title'] ?? $d['name'])),
            descriptionHtml: (string) ($d['summary'] ?? $d['description'] ?? ''),
            images: array_keys($images),
            tags: array_values(array_filter(array_map(fn ($t) => is_string($t) ? $t : (string) ($t['name'] ?? ''), (array) ($d['tags'] ?? [])))),
            license: $license === '' ? '' : (str_starts_with($license, 'CC') || $license === 'STANDARD' ? $license : 'CC '.$license),
            isRemix: $original !== null,
            remixSourceUrl: is_array($original) ? ($original['url'] ?? (! empty($original['id']) ? self::SITE.'/en/models/'.$original['id'] : null)) : null,
            authorId: (string) ($creator['uid'] ?? ''),
            authorName: (string) ($creator['handle'] ?? $creator['name'] ?? ''),
            files: array_values(array_unique($files)),
            category: $d['categories'][0]['name'] ?? null,
        );
    }
}
