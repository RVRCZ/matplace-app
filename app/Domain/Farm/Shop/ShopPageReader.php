<?php

namespace App\Domain\Farm\Shop;

use Masterminds\HTML5;

/**
 * What an e-shop's product page says, read without a browser (TODO #71, docs/AL.md §21): the JSON-LD `Product`
 * (name, brand, pictures, offers, weight), the `og:*` and `product:*` metas, a Shopify product JSON when the shop is
 * one (title, vendor, body, variants with their grams, pictures), the visible text without scripts, menus and footers
 * (cut at about 20 000 characters), and up to eight candidate pictures: the product's own first (JSON-LD, og:image,
 * Shopify), then the gallery's, never the recommended products'. The result is plain arrays for the model's prompt.
 *
 * @phpstan-type Reading array{url: string, host: string, title: string, jsonld: array<string, mixed>|null, types: list<string>, og: array<string, string>, shopify: array<string, mixed>|null, text: string, images: list<array{url: string, from: string, alt: string}>, signals: array{product: bool, listing: bool}}
 */
final class ShopPageReader
{
    public const MAX_TEXT = 20000;

    public const MAX_IMAGES = 8;

    /** Where a picture must not come from: the rest of the shop around the product. */
    private const SKIP_CONTAINERS = '/(related|recommend|similar|upsell|cross-?sell|also-?bought|bestseller|footer|header|navbar|navigation|menu|breadcrumb|cookie|newsletter|review|rating|badge|payment|logo|social|banner|variant[-_]?item|variants|swatch|thumbnail-?skeleton)/i';

    /** A picture that is no product shot by its address alone. */
    private const SKIP_URLS = '~(\.svg(\?|$)|^data:|/flag|/flags/|/graphics/(languages|countries)/|logo|icon|sprite|payment|/tr\?id=|pixel|1x1|spacer|avatar|width=(\d|[1-9]\d)[,&]|\b(16|24|32|45|48|64)x(16|24|32|45|48|64)\b)~i';

    /** @return Reading */
    public function read(string $url, string $html): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $doc = $this->document($html);
        $xpath = new \DOMXPath($doc);

        $jsonld = $this->jsonLd($xpath);
        $og = $this->metas($xpath);
        $title = trim((string) ($og['og:title'] ?? $this->text($xpath->query('//title')->item(0)) ?? ''));
        $product = $jsonld['product'];
        $images = [];
        foreach ((array) ($product['images'] ?? []) as $src) {
            $this->addImage($images, $url, (string) $src, 'jsonld', (string) ($product['name'] ?? ''));
        }
        if (! empty($og['og:image'])) {
            $this->addImage($images, $url, $og['og:image'], 'og', $title);
        }
        foreach ($this->galleryImages($xpath, $url) as [$src, $alt]) {
            $this->addImage($images, $url, $src, 'gallery', $alt);
        }
        $shopify = null;
        if (preg_match('~/products/[a-z0-9-]+~i', $url) && (str_contains($html, 'cdn.shopify.com') || str_contains($html, 'Shopify.'))) {
            $shopify = ['handle' => $this->shopifyHandle($url), 'variant' => self::variantOf($url)];
        }
        // microdata (itemtype schema.org/Product) or a platform's own marker (Upgates: "pageType":"product") count as the page saying "product"
        $marked = $xpath->query('//*[contains(@itemtype, "schema.org/Product") or contains(@itemtype, "schema.org/Offer")]')->length > 0 || (bool) preg_match('/"pageType"\s*:\s*"product"/', $html);

        return [
            'url' => $url, 'host' => $host, 'title' => $title,
            'jsonld' => $product, 'types' => $jsonld['types'], 'og' => $og, 'shopify' => $shopify,
            'text' => $this->visibleText($xpath, $doc),
            'images' => self::listed($images),
            'signals' => [
                'product' => $product !== null || $marked || in_array(strtolower((string) ($og['og:type'] ?? '')), ['product', 'og:product', 'product.item'], true) || $shopify !== null,
                'listing' => $product === null && (bool) array_intersect(['ItemList', 'CollectionPage', 'OfferCatalog', 'SearchResultsPage'], $jsonld['types']),
            ],
        ];
    }

    /**
     * A Shopify product read from `/products/<handle>.json` (what the shop hands out when it serves bots a 429 for the
     * HTML): title, vendor, type, the body as text, options, variants with their grams and prices, pictures (the chosen
     * variant's first when the address names one).
     *
     * @param  array<string, mixed>  $product  the `product` object
     * @return Reading
     */
    public function readShopify(string $url, array $product): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $variantId = self::variantOf($url);
        $variants = [];
        $chosen = null;
        foreach ((array) ($product['variants'] ?? []) as $v) {
            $row = [
                'id' => (int) ($v['id'] ?? 0), 'title' => (string) ($v['title'] ?? ''), 'price' => (string) ($v['price'] ?? ''),
                'grams' => isset($v['grams']) ? (int) $v['grams'] : null, 'sku' => (string) ($v['sku'] ?? ''), 'available' => (bool) ($v['available'] ?? false),
                'options' => array_values(array_filter([$v['option1'] ?? null, $v['option2'] ?? null, $v['option3'] ?? null], fn ($o) => $o !== null && $o !== '')),
                'image_id' => (int) (($v['featured_image']['id'] ?? $v['image_id'] ?? 0) ?: 0),
            ];
            if ($variantId && $row['id'] === $variantId) {
                $row['chosen'] = true;
                $chosen = $row;
            }
            $variants[] = $row;
        }
        $images = [];
        $pictures = (array) ($product['images'] ?? []);
        if ($chosen && $chosen['image_id']) {
            foreach ($pictures as $im) {
                if ((int) ($im['id'] ?? 0) === $chosen['image_id']) {
                    $this->addImage($images, $url, (string) ($im['src'] ?? ''), 'shopify_variant', (string) ($im['alt'] ?? ''));
                }
            }
        }
        foreach ($pictures as $im) {
            $this->addImage($images, $url, (string) ($im['src'] ?? ''), 'shopify', (string) ($im['alt'] ?? ''));
        }
        $body = $this->stripTags((string) ($product['body_html'] ?? ''));
        $text = trim((string) ($product['title'] ?? '')."\n".$body."\n".implode("\n", array_map(fn ($v) => $v['title'].($v['grams'] ? ' ('.$v['grams'].' g shipping weight)' : '').' '.$v['price'], $variants)));

        return [
            'url' => $url, 'host' => $host, 'title' => (string) ($product['title'] ?? ''),
            'jsonld' => null, 'types' => [], 'og' => [],
            'shopify' => [
                'handle' => (string) ($product['handle'] ?? $this->shopifyHandle($url)), 'title' => (string) ($product['title'] ?? ''), 'vendor' => (string) ($product['vendor'] ?? ''),
                'type' => (string) ($product['product_type'] ?? ''), 'tags' => (array) ($product['tags'] ?? []),
                'options' => array_map(fn ($o) => ['name' => (string) ($o['name'] ?? ''), 'values' => array_slice((array) ($o['values'] ?? []), 0, 40)], (array) ($product['options'] ?? [])),
                'variants' => array_slice($variants, 0, 60), 'variant' => $variantId, 'chosen' => $chosen,
            ],
            'text' => mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, self::MAX_TEXT),
            'images' => self::listed($images),
            'signals' => ['product' => true, 'listing' => false],
        ];
    }

    /** `?variant=123` of a Shopify address: the colour the admin had open. */
    public static function variantOf(string $url): ?int
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return isset($q['variant']) && is_numeric($q['variant']) ? (int) $q['variant'] : null;
    }

    /** `https://shop/products/<handle>.json` for a Shopify product address, null for any other. */
    public static function shopifyJsonUrl(string $url): ?string
    {
        if (! preg_match('~^(https?://[^/]+)/(?:[a-z]{2}(?:-[a-z]{2})?/)?(?:collections/[^/]+/)?products/([a-z0-9][a-z0-9-]*)~i', $url, $m)) {
            return null;
        }

        return $m[1].'/products/'.$m[2].'.json';
    }

    private function shopifyHandle(string $url): string
    {
        return preg_match('~/products/([a-z0-9][a-z0-9-]*)~i', $url, $m) ? $m[1] : '';
    }

    private function document(string $html): \DOMDocument
    {
        if (! str_contains(substr($html, 0, 2048), 'charset') && mb_check_encoding($html, 'UTF-8')) {
            $html = '<meta charset="utf-8">'.$html;
        }
        try {
            $doc = (new HTML5(['disable_html_ns' => true]))->loadHTML($html);
        } catch (\Throwable) {
            $doc = new \DOMDocument;
            @$doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        }

        return $doc;
    }

    /**
     * The `Product` of the page's JSON-LD (the first one, also inside `@graph`), flattened, and every `@type` seen.
     *
     * @return array{product: array<string, mixed>|null, types: list<string>}
     */
    private function jsonLd(\DOMXPath $xpath): array
    {
        $types = [];
        $product = null;
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $data = json_decode(trim((string) $node->textContent), true);
            if (! is_array($data)) {
                continue;
            }
            $queue = array_is_list($data) ? $data : [$data];
            while ($queue) {
                $item = array_shift($queue);
                if (! is_array($item)) {
                    continue;
                }
                foreach ((array) ($item['@graph'] ?? []) as $g) {
                    $queue[] = $g;
                }
                foreach ((array) ($item['@type'] ?? []) as $t) {
                    if (is_string($t)) {
                        $types[] = $t;
                    }
                }
                $isProduct = (bool) array_intersect(['Product', 'ProductGroup', 'IndividualProduct'], (array) ($item['@type'] ?? []));
                if ($isProduct && $product === null) {
                    $product = $this->flattenProduct($item);
                }
            }
        }

        return ['product' => $product, 'types' => array_values(array_unique($types))];
    }

    /** @param  array<string, mixed>  $p */
    private function flattenProduct(array $p): array
    {
        $name = fn ($v) => is_array($v) ? (string) ($v['name'] ?? '') : (string) $v;
        $images = [];
        foreach ((array) ($p['image'] ?? []) as $im) {
            $src = is_array($im) ? (string) ($im['url'] ?? $im['contentUrl'] ?? '') : (string) $im;
            if ($src !== '') {
                $images[] = $src;
            }
        }
        $offers = [];
        $raw = $p['offers'] ?? [];
        foreach (array_is_list((array) $raw) ? (array) $raw : [(array) $raw] as $o) {
            if (! is_array($o)) {
                continue;
            }
            $offers[] = ['price' => (string) ($o['price'] ?? $o['lowPrice'] ?? ''), 'currency' => (string) ($o['priceCurrency'] ?? ''), 'availability' => basename((string) ($o['availability'] ?? ''))];
        }
        $weight = $p['weight'] ?? null;
        $weightText = is_array($weight) ? trim(($weight['value'] ?? '').' '.($weight['unitCode'] ?? $weight['unitText'] ?? '')) : (string) $weight;

        return [
            'name' => $name($p['name'] ?? ''), 'brand' => $name($p['brand'] ?? ''), 'description' => mb_substr($this->stripTags((string) ($p['description'] ?? '')), 0, 2000),
            'sku' => (string) ($p['sku'] ?? ''), 'color' => $name($p['color'] ?? ''), 'material' => $name($p['material'] ?? ''), 'weight' => $weightText,
            'images' => array_values(array_unique($images)), 'offers' => array_slice($offers, 0, 5),
        ];
    }

    /** @return array<string, string> og:*, product:*, twitter:image, description */
    private function metas(\DOMXPath $xpath): array
    {
        $out = [];
        foreach ($xpath->query('//meta[@content]') as $m) {
            $key = strtolower(trim((string) ($m->getAttribute('property') ?: $m->getAttribute('name'))));
            if ($key !== '' && (str_starts_with($key, 'og:') || str_starts_with($key, 'product:') || in_array($key, ['twitter:image', 'description'], true)) && ! isset($out[$key])) {
                $out[$key] = trim((string) $m->getAttribute('content'));
            }
        }

        return $out;
    }

    /**
     * The gallery's pictures: every `<img>` of the body outside the menus, footers and recommended products, the
     * largest address of its `srcset`, in document order, gallery-like containers first.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function galleryImages(\DOMXPath $xpath, string $url): array
    {
        $ranked = [];
        foreach ($xpath->query('//body//img | //body//source[@srcset]') as $i => $img) {
            $src = $this->largestSource((string) ($img->getAttribute('srcset') ?: $img->getAttribute('data-srcset'))) ?: (string) ($img->getAttribute('src') ?: $img->getAttribute('data-src') ?: $img->getAttribute('data-lazy-src'));
            if ($src === '' || str_starts_with($src, 'data:')) {
                continue;
            }
            $gallery = false;
            $skip = false;
            for ($n = $img->parentNode; $n instanceof \DOMElement; $n = $n->parentNode) {
                $tag = strtolower($n->tagName);
                if (in_array($tag, ['nav', 'header', 'footer', 'aside'], true)) {
                    $skip = true;
                    break;
                }
                $hint = $n->getAttribute('class').' '.$n->getAttribute('id').' '.$n->getAttribute('data-section');
                if ($hint !== '  ' && preg_match(self::SKIP_CONTAINERS, $hint)) {
                    $skip = true;
                    break;
                }
                if (preg_match('/(gallery|product[-_]?(image|media|photo|pic|gallery|main)|main-?image|slider|swiper|carousel|pd-pics|images-?ajax|zoom|lightbox|detail-?image)/i', $hint)) {
                    $gallery = true;
                }
            }
            if ($skip) {
                continue;
            }
            $alt = trim((string) ($img->getAttribute('alt') ?: $img->getAttribute('title')));
            // a picture with the product's name in its alt text is the product's even outside a gallery container
            $ranked[] = [$gallery ? 0 : 1, $i, $src, $alt];
        }
        usort($ranked, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(fn ($r) => [$r[2], $r[3]], $ranked);
    }

    /** The address with the largest width (or density) of a `srcset`; '' when there is none. */
    private function largestSource(string $srcset): string
    {
        // by whitespace, not by comma: Prusa's addresses carry commas (/cdn-cgi/image/width=400,format=auto,…)
        $best = ['', -1.0];
        $url = null;
        $take = function (?string $u, float $size) use (&$best): void {
            if ($u !== null && $u !== '' && $size >= $best[1]) {
                $best = [$u, $size];
            }
        };
        foreach (preg_split('/\s+/', trim($srcset)) ?: [] as $token) {
            $t = rtrim($token, ',');
            if ($t === '') {
                continue;
            }
            if (preg_match('/^([\d.]+)([wx])$/', $t, $m)) {
                $take($url, (float) $m[1] * ($m[2] === 'x' ? 1000 : 1));
                $url = null;
            } else {
                $take($url, 1.0);
                $url = $t;
            }
        }
        $take($url, 1.0);

        return $best[0];
    }

    /** The size a picture's address hints at (3DJake's .512x512, Prusa's width=1024): to keep the largest of a set. */
    private static function sizeHint(string $url): int
    {
        $n = 0;
        if (preg_match('/\.(\d{2,4})x(\d{2,4})\.\w+/', $url, $m)) {
            $n = max((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/[?&\/,](?:width|w|size)=(\d{2,4})/', $url, $m)) {
            $n = max($n, (int) $m[1]);
        }

        return $n;
    }

    /**
     * The candidates as the reading lists them: at most eight, without the de-duplication key.
     *
     * @param  list<array{url: string, from: string, alt: string, key: string}>  $images
     * @return list<array{url: string, from: string, alt: string}>
     */
    private static function listed(array $images): array
    {
        return array_map(fn (array $i) => ['url' => $i['url'], 'from' => $i['from'], 'alt' => $i['alt']], array_slice($images, 0, self::MAX_IMAGES));
    }

    /** @param  list<array{url: string, from: string, alt: string, key: string}>  $images */
    private function addImage(array &$images, string $base, string $src, string $from, string $alt): void
    {
        $src = trim($src);
        if ($src === '' || preg_match(self::SKIP_URLS, $src)) {
            return;
        }
        $abs = ShopFetcher::absolute($base, $src);
        if (! preg_match('~^https?://~i', $abs)) {
            return;
        }
        // the same picture in another size (Prusa's cdn-cgi widths, 3DJake's 128x128 … 1024x1024) is one candidate
        $key = preg_replace(['~/cdn-cgi/image/[^/]+/~', '~\.\d{2,4}x\d{2,4}(\.\w+)$~', '~[?&](v|width|w|h|size|quality)=[^&]*~'], ['/', '$1', ''], strtolower($abs));
        foreach ($images as $n => $have) {
            if ($have['key'] === $key) {
                // the same picture again, larger (3DJake lists 128 … 1024 px): keep the large address
                if (self::sizeHint($abs) > self::sizeHint($have['url'])) {
                    $images[$n]['url'] = $abs;
                }

                return;
            }
        }
        if (count($images) >= self::MAX_IMAGES) {
            return;
        }
        $images[] = ['url' => $abs, 'from' => $from, 'alt' => mb_substr($alt, 0, 120), 'key' => $key];
    }

    /** The text a person reads: no scripts, styles, menus, headers or footers; whitespace folded; about 20 000 characters. */
    private function visibleText(\DOMXPath $xpath, \DOMDocument $doc): string
    {
        foreach ($xpath->query('//script | //style | //noscript | //svg | //template | //iframe | //nav | //header | //footer | //*[@aria-hidden="true"] | //*[@hidden]') as $node) {
            $node->parentNode?->removeChild($node);
        }
        $body = $xpath->query('//body')->item(0) ?? $doc->documentElement;
        if (! $body) {
            return '';
        }
        // text node by text node: a space between inline pieces ("Nozzle temperature" | "215 °C"), a new line at a block
        $blocks = ['p', 'div', 'li', 'tr', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'dt', 'dd', 'section', 'article', 'table', 'ul', 'ol', 'summary', 'details', 'blockquote', 'main'];
        $text = '';
        foreach ($xpath->query('.//text()', $body) as $node) {
            $piece = preg_replace('/[\s\x{00A0}]+/u', ' ', trim((string) $node->textContent)) ?? '';
            if ($piece === '') {
                continue;
            }
            $parent = $node->parentNode instanceof \DOMElement ? strtolower($node->parentNode->tagName) : '';
            $text .= (in_array($parent, $blocks, true) ? "\n" : ' ').$piece;
            if (mb_strlen($text) > self::MAX_TEXT + 200) {
                break;
            }
        }

        return mb_substr(trim($text), 0, self::MAX_TEXT);
    }

    private function text(?\DOMNode $node): ?string
    {
        return $node ? preg_replace('/\s+/u', ' ', trim((string) $node->textContent)) : null;
    }

    private function stripTags(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('~<(br|/p|/li|/tr|/h\d)[^>]*>~i', "\n", $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text);
    }
}
