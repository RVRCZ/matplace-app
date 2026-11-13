<?php

namespace App\Support;

use App\Models\AnonymousSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Who is a robot. Our statistics count people; search engines, link previews, monitors and scripts are kept apart
 * (events of the type `crawl`, with the robot's family and nothing else about it).
 *
 * A request is a robot's when
 *   its User-Agent names a known robot (FAMILIES) or carries a robot's word (bot, crawl, spider, …),
 *   it does not introduce itself as a browser at all (no "Mozilla/", or no User-Agent),
 *   it is a HEAD request, or it does not ask for a page (no text/html in Accept),
 *   the operator listed its User-Agent or its data centre's address block (config/bots.php: `listed`, `datacenter`),
 *   or it is one repeated header from one address block that never keeps our cookie (`burst`, docs/AP.md §14).
 *
 *   it is a scanner: it asks for addresses of other systems (wp-login.php, /phpinfo, /.env …), or says it came from a
 *   page of a bare IP address or from such an address (scanners set the address they try as the referer).
 *
 * A robot that pretends to be a browser passes all of this. It is told apart later: a real browser runs the page's
 * script, which confirms the visit (Track::seen); a visit nobody confirmed is not counted as a person.
 */
final class Bots
{
    /** Request attribute: the answer of name() for this request ('' = a browser), so the burst counter counts a page once. */
    private const DECIDED = 'bots.name';

    /** family → pieces of a User-Agent (lower case) */
    public const FAMILIES = [
        'google' => ['googlebot', 'google-inspectiontool', 'googleother', 'adsbot-google', 'mediapartners-google', 'storebot-google', 'google-extended', 'apis-google', 'feedfetcher-google', 'google-read-aloud', 'google-site-verification', 'google-safety', 'google-cloudvertexbot', 'chrome-lighthouse', 'google page speed'],
        'bing' => ['bingbot', 'msnbot', 'bingpreview', 'adidxbot', 'microsoftpreview'],
        'seznam' => ['seznambot', 'seznam screenshot', 'seznamhomepagecrawler'],
        'yandex' => ['yandex'],
        'duckduckgo' => ['duckduckbot', 'duckassistbot'],   // not "duckduckgo": that is also the name of a browser
        'apple' => ['applebot'],
        'baidu' => ['baiduspider'],
        'petal' => ['petalbot', 'aspiegelbot'],
        'ahrefs' => ['ahrefsbot', 'ahrefssiteaudit'],
        'semrush' => ['semrush', 'siteauditbot', 'splitsignalbot'],
        'seranking' => ['seranking', 'se ranking'],
        // tools that audit sites and collect links for their customers: nobody's visit (docs/AP.md §14)
        'seo' => ['serpstat', 'seokicks', 'blexbot', 'barkrowler', 'sistrix', 'screaming frog', 'megaindex', 'linkdex', 'seobility', 'siteimprove', 'seznamzbozi', 'netcraft', 'censys', 'zoominfo', 'awario', 'imagesift', 'linkpad', 'spbot', 'sitechecker', 'seostar', 'neticle', 'similartech', 'moz.com', 'ubersuggest', 'wpbot', 'keys-so', 'serpapi', 'dataprovider', 'expanse', 'internetmeasurement', 'cyberscan', 'bitsight'],
        'majestic' => ['mj12bot'],
        'dotbot' => ['dotbot', 'rogerbot'],
        'dataforseo' => ['dataforseobot'],
        'bytedance' => ['bytespider', 'tiktokspider'],
        'amazon' => ['amazonbot'],
        'openai' => ['gptbot', 'chatgpt-user', 'oai-searchbot'],
        'anthropic' => ['claudebot', 'claude-user', 'claude-searchbot', 'anthropic-ai'],
        'perplexity' => ['perplexitybot', 'perplexity-user'],
        'commoncrawl' => ['ccbot'],
        'meta' => ['facebookexternalhit', 'facebookcatalog', 'meta-externalagent', 'meta-externalfetcher', 'facebot'],
        'social' => ['twitterbot', 'linkedinbot', 'pinterestbot', 'slackbot', 'discordbot', 'telegrambot', 'whatsapp', 'skypeuripreview', 'redditbot'],
        'archive' => ['archive.org_bot', 'ia_archiver'],
        'monitor' => ['uptimerobot', 'pingdom', 'statuscake', 'site24x7', 'betteruptime', 'gtmetrix'],
    ];

    /** words no browser carries in its name */
    private const WORDS = '/bot|crawl|spider|slurp|preview|lighthouse|pagespeed|headless|phantomjs|curl|wget|python|monitor|scan|fetch|feed|archiver|validator|scrapy|httpclient|okhttp|java\/|go-http|libwww|axios|node-fetch|guzzle/i';

    /**
     * Addresses only a scanner asks for: other systems' pages and files, server secrets, spam slugs (docs/O.md).
     * None of them is a page of ours; old WordPress addresses of matplace.cz redirect (301) since 17 Oct 2026.
     */
    private const SCANNER_PATH = '~^/(?:(?:wp|wordpress)(?:[/-]|$)|.*\.(?:php\d?|asp|aspx|jsp|cgi|env|ini|sql|bak|yml|yaml|log)$|(?:.*/)?\.[a-z0-9_-]+|_profiler|phpinfo|credentials|vendor/|cgi-bin|phpmyadmin|pma(?:/|$)|actuator|solr|_ignition|telescope|owa(?:/|$)|autodiscover|boaform|hnap1|server-status|\d{3,}/[a-z0-9-]+)~i';

    /** Is it a scanner: the address asked for, or the page it says it came from? */
    public static function scanner(string $path, ?string $referer = null): bool
    {
        if (preg_match(self::SCANNER_PATH, '/'.ltrim($path, '/')) === 1) {
            return true;
        }
        $referer = trim((string) $referer);
        if ($referer === '') {
            return false;
        }
        $host = trim((string) parse_url($referer, PHP_URL_HOST), '[]');
        // nobody follows a link from a page of a bare IP address (ours, 178.104.162.164, or anyone's): a scanner wrote it
        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }
        $from = (string) parse_url($referer, PHP_URL_PATH);

        return $from !== '' && preg_match(self::SCANNER_PATH, '/'.ltrim($from, '/')) === 1;
    }

    /** The robot's family, `other` for a robot nobody listed, null for a browser. */
    public static function byAgent(?string $agent): ?string
    {
        $agent = trim((string) $agent);
        if ($agent === '') {
            return 'other';
        }
        $lower = strtolower($agent);
        foreach (self::FAMILIES as $family => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    return $family;
                }
            }
        }

        // every real browser introduces itself as Mozilla; libraries, monitors and scripts do not
        return ! str_starts_with($agent, 'Mozilla/') || preg_match(self::WORDS, $agent) === 1 ? 'other' : null;
    }

    /**
     * A robot by what the operator listed (config/bots.php): a User-Agent string no browser sends (`listed`), or an
     * address block of a data centre (`datacenter`). Null for everybody else.
     */
    public static function byList(?string $agent, ?string $ip): ?string
    {
        $agent = strtolower(trim((string) $agent));
        foreach ((array) config('bots.agents', []) as $needle) {
            if ($needle !== '' && $agent !== '' && str_contains($agent, strtolower((string) $needle))) {
                return 'listed';
            }
        }

        return self::inNetworks((string) $ip, (array) config('bots.networks', [])) ? 'datacenter' : null;
    }

    /** Is the address inside one of the blocks ("203.0.113.0/24", "2001:db8::/32", or one address)? */
    public static function inNetworks(string $ip, array $blocks): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        foreach ($blocks as $block) {
            [$net, $bits] = array_pad(explode('/', (string) $block, 2), 2, null);
            $base = @inet_pton((string) $net);
            if ($base === false || strlen($base) !== strlen($packed)) {
                continue;
            }
            $bits = $bits === null ? strlen($base) * 8 : max(0, min(strlen($base) * 8, (int) $bits));
            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;
            if (substr($packed, 0, $bytes) !== substr($base, 0, $bytes)) {
                continue;
            }
            if ($rest === 0 || (ord($packed[$bytes]) >> (8 - $rest)) === (ord($base[$bytes]) >> (8 - $rest))) {
                return true;
            }
        }

        return false;
    }

    /** One network: IPv4 by its /24, IPv6 by its /48 (one provider's customer, one data centre's rack). */
    public static function network(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return '';
        }

        return strlen($packed) === 4 ? bin2hex(substr($packed, 0, 3)).'/24' : bin2hex(substr($packed, 0, 6)).'/48';
    }

    /**
     * A robot known without counting anything: by its name, or by the operator's lists. What decides whether a request
     * gets an anonymous session at all (EnsureAnonymousSession) and whether the start page may send it to a language.
     */
    public static function known(Request $request): ?string
    {
        return self::byAgent($request->userAgent()) ?? self::byList($request->userAgent(), $request->ip());
    }

    /**
     * One repeated header from one address block (config/bots.php `burst_per_hour`): pages asked for without our cookie
     * by the same User-Agent from the same network, counted per clock hour. A browser keeps the cookie from its first
     * page on, so a person adds one; a machine that drops it adds one per page and is a robot from the limit on.
     */
    private static function burst(Request $request): bool
    {
        $limit = (int) config('bots.burst_per_hour', 0);
        $network = self::network((string) $request->ip());
        if ($limit <= 0 || $network === '' || ! $request->isMethod('GET') || $request->ajax() || $request->cookies->has(AnonymousSession::COOKIE)) {
            return false;
        }
        try {
            $key = 'bots:burst:'.now()->format('YmdH').':'.sha1($request->userAgent().'|'.$network);
            Cache::add($key, 0, 3700);

            return (int) Cache::increment($key) > $limit;
        } catch (\Throwable) {
            return false;   // counting must never break a page
        }
    }

    /** The same for a whole request: besides the name, how it asks. Decided once per request. */
    public static function name(Request $request): ?string
    {
        if ($request->attributes->has(self::DECIDED)) {
            return $request->attributes->get(self::DECIDED) ?: null;
        }
        $name = self::decide($request);
        $request->attributes->set(self::DECIDED, $name ?? '');

        return $name;
    }

    private static function decide(Request $request): ?string
    {
        $family = self::known($request);
        if ($family !== null) {
            return $family;
        }
        if (self::scanner($request->path(), $request->headers->get('referer'))) {
            return 'scanner';
        }
        if ($request->isMethod('HEAD')) {
            return 'other';
        }
        // a browser opening a page says it wants HTML; a script fetching addresses says */* or nothing
        if ($request->isMethod('GET') && ! $request->ajax() && ! str_contains((string) $request->headers->get('Accept'), 'text/html')) {
            return 'other';
        }

        // the last question, and only of a page: every earlier answer is free, this one counts
        return str_contains((string) $request->headers->get('Accept'), 'text/html') && self::burst($request) ? 'burst' : null;
    }

    public static function is(Request $request): bool
    {
        return self::name($request) !== null;
    }
}
