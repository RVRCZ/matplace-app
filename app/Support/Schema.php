<?php

namespace App\Support;

use App\Models\Post;

/**
 * Structured data (schema.org) as plain arrays; <x-jsonld> prints them. One place knows how each kind of page
 * describes itself, so the pages only say what they are.
 */
final class Schema
{
    /** Without empty values, at any depth (a null price or an empty list must not reach the page). */
    public static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = self::clean($value);
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /** The thing as the text of a <script type="application/ld+json">; null when nothing is left to say. */
    public static function json(array $data): ?string
    {
        $thing = self::clean($data);
        unset($thing['@context']);

        return $thing ? (string) json_encode(['@context' => 'https://schema.org'] + $thing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) : null;
    }

    public static function organization(): array
    {
        return [
            '@type' => 'Organization', '@id' => url('/').'#organization', 'name' => 'matplace', 'url' => url('/'),
            'logo' => url('/img/logo.png'),
            'sameAs' => array_values(array_filter([config('youtube.channel_url'), config('seo.same_as.facebook'), config('seo.same_as.instagram')])),
        ];
    }

    /** The site with its search box: results open in the inspiration catalogue. */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite', '@id' => url('/').'#website', 'name' => 'matplace', 'url' => localized_route('home'), 'inLanguage' => app()->getLocale(),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => localized_route('catalog.index').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function article(Post $post): array
    {
        return [
            '@type' => 'Article', 'headline' => $post->titleIn(), 'description' => $post->excerptIn(), 'inLanguage' => app()->getLocale(),
            'image' => $post->coverUrl(), 'datePublished' => $post->published_at?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(),
            'mainEntityOfPage' => $post->publicUrl(),
            'author' => ['@type' => $post->author_id ? 'Person' : 'Organization', 'name' => $post->author?->name ?: ($post->author_name ?: 'matplace')],
            'publisher' => ['@type' => 'Organization', 'name' => 'matplace', 'logo' => ['@type' => 'ImageObject', 'url' => url('/img/logo.png')]],
        ];
    }

    /**
     * "How it works" of a tool.
     *
     * @param  list<array{name: string, text: string}>  $steps
     * @param  list<string>  $images
     */
    public static function howTo(string $name, string $description, array $steps, string $url, array $images = []): array
    {
        return [
            '@type' => 'HowTo', 'name' => $name, 'description' => $description, 'image' => $images, 'inLanguage' => app()->getLocale(),
            'step' => array_values(array_map(fn (array $s, int $i) => ['@type' => 'HowToStep', 'position' => $i + 1, 'name' => $s['name'], 'text' => $s['text'], 'url' => $url.'#step-'.($i + 1)], $steps, array_keys($steps))),
        ];
    }

    /** @param  list<array{q: string, a: string}>  $items */
    public static function faq(array $items): array
    {
        return [
            '@type' => 'FAQPage', 'inLanguage' => app()->getLocale(),
            'mainEntity' => array_values(array_map(fn (array $i) => ['@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']]], $items)),
        ];
    }

    /**
     * A list of pages in order (a collection, a list of articles).
     *
     * @param  list<array{name: string, url: string, image?: ?string}>  $items
     */
    public static function itemList(string $name, array $items, ?string $description = null): array
    {
        return [
            '@type' => 'ItemList', 'name' => $name, 'description' => $description, 'numberOfItems' => count($items),
            'itemListElement' => array_values(array_map(fn (array $i, int $n) => ['@type' => 'ListItem', 'position' => $n + 1, 'name' => $i['name'], 'url' => $i['url'], 'image' => $i['image'] ?? null], $items, array_keys($items))),
        ];
    }

    /** @param  list<array{0: string, 1: string}>  $trail  [name, address] from the home page down */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(fn (array $t, int $n) => ['@type' => 'ListItem', 'position' => $n + 1, 'name' => $t[0], 'item' => $t[1]], $trail, array_keys($trail))),
        ];
    }
}
