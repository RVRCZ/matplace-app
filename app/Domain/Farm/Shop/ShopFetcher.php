<?php

namespace App\Domain\Farm\Shop;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Plain HTTP to an e-shop, with the guards a server that fetches addresses typed in by a person needs (TODO #71,
 * docs/AL.md §21): http(s) only, a public host only – the address is resolved and private, loopback and link-local
 * ranges are refused, after every redirect too –, 20 s a request, 3 MB of HTML, 6 MB a picture, 5 redirects. There
 * is no browser on the server (headless Chrome answers 503 there), so what the HTML says without scripts is all we get.
 */
final class ShopFetcher
{
    public const MAX_HTML = 3 * 1024 * 1024;

    public const MAX_IMAGE = 6 * 1024 * 1024;

    public const TIMEOUT = 20;

    public const MAX_REDIRECTS = 5;

    /** A browser's signature with our name at the end: a shop that serves bots a stub serves this a page. */
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 matplace-filament/1.0';

    /** Query parameters that only track the visitor; the same product with and without them is the same product. */
    private const TRACKING = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'fbclid', 'gclid', 'dclid', 'msclkid', 'mc_cid', 'mc_eid', 'ref', 'srsltid', '_pos', '_sid', '_ss', '_psq', '_ga', 'igshid', 'yclid', 'ttclid'];

    /** @var (\Closure(string): list<string>)|null tests: host → addresses, instead of DNS */
    public static ?\Closure $resolver = null;

    /**
     * The address in its one stored form: http(s), lower-case host, no tracking parameters, the rest of the query in
     * a fixed order, no fragment. Null when it is not a web address. A Shopify `?variant=` stays: it is the colour.
     */
    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        $p = parse_url($url);
        if (! is_array($p) || empty($p['host']) || ! in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true)) {
            return null;
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        $host = strtolower(rtrim($p['host'], '.'));
        if ($host === '' || strlen($host) > 253) {
            return null;
        }
        $query = [];
        parse_str((string) ($p['query'] ?? ''), $query);
        foreach (array_keys($query) as $k) {
            if (in_array(strtolower((string) $k), self::TRACKING, true) || str_starts_with(strtolower((string) $k), 'utm_')) {
                unset($query[$k]);
            }
        }
        ksort($query);
        $port = isset($p['port']) && ! in_array((int) $p['port'], [80, 443], true) ? ':'.$p['port'] : '';
        $path = (string) ($p['path'] ?? '');
        $path = $path === '' ? '/' : preg_replace('~/{2,}~', '/', $path);

        return strtolower($p['scheme']).'://'.$host.$port.$path.($query ? '?'.http_build_query($query) : '');
    }

    /**
     * Refuses an address that does not point at the public web: another scheme, a host that is ours or private
     * (localhost, .local, .internal), one that resolves to a private, loopback, link-local or reserved range, or one
     * that does not resolve at all.
     *
     * @throws ShopRefused
     */
    public static function assertPublic(string $url): void
    {
        $p = parse_url($url);
        if (! is_array($p) || ! in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true) || empty($p['host'])) {
            throw new ShopRefused(ShopRefused::SCHEME, 'Only http(s) addresses are read.');
        }
        $host = strtolower(rtrim((string) $p['host'], '.'));
        if ($host === 'localhost' || preg_match('/\.(localhost|local|internal|lan|home|intranet|corp|arpa|test|invalid|example)$/', $host) || $host === 'example.com') {
            throw new ShopRefused(ShopRefused::PRIVATE_HOST, 'The host is not on the public web: '.$host);
        }
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            if (! self::isPublicAddress($literal)) {
                throw new ShopRefused(ShopRefused::PRIVATE_HOST, 'The address is not public: '.$literal);
            }

            return;
        }
        $addresses = self::resolve($host);
        if ($addresses === []) {
            throw new ShopRefused(ShopRefused::UNRESOLVED, 'The host does not resolve: '.$host);
        }
        foreach ($addresses as $ip) {
            if (! self::isPublicAddress($ip)) {
                throw new ShopRefused(ShopRefused::PRIVATE_HOST, 'The host resolves to a private address: '.$host.' → '.$ip);
            }
        }
    }

    /** Not private (10/8, 172.16/12, 192.168/16, fc00::/7), not reserved (0/8, 127/8, 169.254/16, 240/4, ::1, fe80::/10, …). */
    public static function isPublicAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        // IPv4 mapped into IPv6 (::ffff:10.0.0.1) and the 100.64/10 carrier range PHP does not count as private
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            return self::isPublicAddress($m[1]);
        }
        if (preg_match('/^100\.(\d+)\./', $ip, $m) && (int) $m[1] >= 64 && (int) $m[1] <= 127) {
            return false;
        }

        return true;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (self::$resolver) {
            return array_values(array_filter((array) (self::$resolver)($host), 'is_string'));
        }
        $out = [];
        foreach ((array) (@gethostbynamel($host) ?: []) as $ip) {
            $out[] = (string) $ip;
        }
        foreach ((array) (@dns_get_record($host, DNS_AAAA) ?: []) as $r) {
            if (! empty($r['ipv6'])) {
                $out[] = (string) $r['ipv6'];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * One GET with the guards, following redirects by hand so every hop is checked. Returns the final address, the
     * status, the content type and the body (read up to $maxBytes; more is refused).
     *
     * @param  array<string, string>  $headers
     * @return array{url: string, status: int, type: string, body: string}
     *
     * @throws ShopRefused
     */
    public function get(string $url, array $headers = [], int $maxBytes = self::MAX_HTML): array
    {
        $current = $url;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            self::assertPublic($current);
            try {
                $res = Http::timeout(self::TIMEOUT)->connectTimeout(10)
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => 'cs,en;q=0.8'] + $headers)
                    ->get($current);
            } catch (\Throwable $e) {
                throw new ShopRefused(ShopRefused::UNREADABLE, 'The shop did not answer: '.mb_substr($e->getMessage(), 0, 200));
            }
            $status = $res->status();
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = (string) $res->header('Location');
                if ($location === '') {
                    throw new ShopRefused(ShopRefused::REDIRECTS, 'A redirect without a target.');
                }
                $current = self::absolute($current, $location);

                continue;
            }
            if ((int) ($res->header('Content-Length') ?: 0) > $maxBytes) {
                throw new ShopRefused(ShopRefused::TOO_BIG, 'The answer is larger than '.$maxBytes.' bytes.');
            }

            return ['url' => $current, 'status' => $status, 'type' => strtolower((string) $res->header('Content-Type')), 'body' => self::read($res, $maxBytes)];
        }
        throw new ShopRefused(ShopRefused::REDIRECTS, 'More than '.self::MAX_REDIRECTS.' redirects.');
    }

    /**
     * A picture of a product, as bytes; null when it cannot be had (any reason: the card goes on without it).
     */
    public function image(string $url): ?string
    {
        try {
            $r = $this->get($url, ['Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8'], self::MAX_IMAGE);
        } catch (ShopRefused) {
            return null;
        }
        if ($r['status'] !== 200 || $r['body'] === '' || (str_contains($r['type'], 'text/html'))) {
            return null;
        }

        return $r['body'];
    }

    /** `Location` or a picture's `src` against the page it came from. */
    public static function absolute(string $base, string $ref): string
    {
        $ref = trim($ref);
        if ($ref === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $ref)) {
            return $ref;
        }
        $b = parse_url($base) ?: [];
        $origin = ($b['scheme'] ?? 'https').'://'.($b['host'] ?? '').(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($ref, '//')) {
            return ($b['scheme'] ?? 'https').':'.$ref;
        }
        if (str_starts_with($ref, '/')) {
            return $origin.$ref;
        }
        if (str_starts_with($ref, '?')) {
            return $origin.($b['path'] ?? '/').$ref;
        }
        $dir = preg_replace('~[^/]*$~', '', (string) ($b['path'] ?? '/'), 1);

        return $origin.($dir === '' ? '/' : $dir).$ref;
    }

    /** The body, read in pieces so that a shop cannot hand us a gigabyte. */
    private static function read(Response $res, int $maxBytes): string
    {
        $stream = $res->toPsrResponse()->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $body = '';
        while (! $stream->eof()) {
            $chunk = $stream->read(65536);
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
            if (strlen($body) > $maxBytes) {
                throw new ShopRefused(ShopRefused::TOO_BIG, 'The answer is larger than '.$maxBytes.' bytes.');
            }
        }

        return $body;
    }
}
