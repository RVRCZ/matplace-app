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

    /**
     * Who runs the site: the short form on the home page; the full one ($full, the about and contact pages) adds the legal
     * name, the registered office and the company number from config/seo.php `organization`, the facts the contact page
     * prints (docs/AN.md §12.4). LocalBusiness with opening hours waits for a pickup in person with hours (Roman decides).
     */
    public static function organization(bool $full = false): array
    {
        $data = [
            '@type' => 'Organization', '@id' => url('/').'#organization', 'name' => 'matplace', 'url' => url('/'),
            'logo' => url('/img/logo.png'),
            'sameAs' => array_values(array_filter([config('youtube.channel_url'), config('seo.same_as.facebook'), config('seo.same_as.instagram')])),
        ];
        $org = (array) config('seo.organization', []);
        if (! $full || $org === []) {
            return $data;
        }

        return $data + [
            'legalName' => $org['legal_name'] ?? null,
            'email' => $org['email'] ?? null,
            'address' => empty($org['street']) ? null : [
                '@type' => 'PostalAddress', 'streetAddress' => $org['street'], 'addressLocality' => $org['locality'] ?? null,
                'postalCode' => $org['postal_code'] ?? null, 'addressCountry' => $org['country'] ?? null,
            ],
            // the company number (IČO): schema.org has no field of its own for it
            'identifier' => empty($org['ico']) ? null : ['@type' => 'PropertyValue', 'propertyID' => 'IČO', 'value' => (string) $org['ico']],
            'vatID' => $org['vat_id'] ?? null,
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

    /** A tool's video: what search engines show as a video result (the YouTube player lives on the page, no cookies before a click). */
    public static function video(string $name, string $description, string $thumbnail, string $youtubeId, string $uploaded = '', int $seconds = 60): array
    {
        return [
            '@type' => 'VideoObject', 'name' => $name, 'description' => $description, 'thumbnailUrl' => [$thumbnail], 'inLanguage' => app()->getLocale(),
            'uploadDate' => $uploaded !== '' ? $uploaded : null, 'duration' => sprintf('PT%dM%dS', intdiv($seconds, 60), $seconds % 60),
            'embedUrl' => 'https://www.youtube-nocookie.com/embed/'.$youtubeId, 'contentUrl' => 'https://www.youtube.com/watch?v='.$youtubeId,
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

    /**
     * A thing we sell a print of: an inspiration model with a slice of its own file (docs/AN.md §13). The offers are the
     * price of one piece in each material as one AggregateOffer in the visitor's currency, sold by us; only where the
     * licence allows a sale – elsewhere the page stays a CreativeWork alone.
     *
     * @param  list<string>  $images
     * @param  list<float>  $prices  one per material, in $currency
     */
    public static function product(string $name, ?string $description, array $images, string $url, array $prices, string $currency): array
    {
        return [
            '@type' => 'Product', 'name' => $name, 'description' => $description, 'image' => $images, 'url' => $url,
            'offers' => $prices ? [
                '@type' => 'AggregateOffer', 'priceCurrency' => $currency, 'lowPrice' => round(min($prices), 2), 'highPrice' => round(max($prices), 2), 'offerCount' => count($prices),
                'availability' => 'https://schema.org/InStock', 'itemCondition' => 'https://schema.org/NewCondition', 'url' => $url,
                'seller' => ['@id' => url('/').'#organization'],
            ] : null,
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
