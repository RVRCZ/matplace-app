<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Who is a robot. Our statistics count people; search engines, link previews, monitors and scripts are kept apart
 * (events of the type `crawl`, with the robot's family and nothing else about it).
 *
 * A request is a robot's when
 *   its User-Agent names a known robot (FAMILIES) or carries a robot's word (bot, crawl, spider, …),
 *   it does not introduce itself as a browser at all (no "Mozilla/", or no User-Agent),
 *   it is a HEAD request, or it does not ask for a page (no text/html in Accept).
 *
 * A robot that pretends to be a browser passes all of this. It is told apart later: a real browser runs the page's
 * script, which confirms the visit (Track::seen); a visit nobody confirmed is not counted as a person.
 */
final class Bots
{
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
        'semrush' => ['semrushbot', 'siteauditbot', 'splitsignalbot'],
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

    /** The same for a whole request: besides the name, how it asks. */
    public static function name(Request $request): ?string
    {
        $family = self::byAgent($request->userAgent());
        if ($family !== null) {
            return $family;
        }
        if ($request->isMethod('HEAD')) {
            return 'other';
        }
        // a browser opening a page says it wants HTML; a script fetching addresses says */* or nothing
        if ($request->isMethod('GET') && ! $request->ajax() && ! str_contains((string) $request->headers->get('Accept'), 'text/html')) {
            return 'other';
        }

        return null;
    }

    public static function is(Request $request): bool
    {
        return self::name($request) !== null;
    }
}
