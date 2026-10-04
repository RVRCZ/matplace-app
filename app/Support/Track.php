<?php

namespace App\Support;

use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as MatchedRoute;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Our own statistics, written to `events`. First-party and without any extra cookie: a visitor is the anonymous
 * session every visitor already has. Recording never breaks the page that calls it. What the numbers mean: docs/O.md.
 *
 * People only:
 *   robots   are no visitors: a page they fetch is one `crawl` (which robot, which page), nothing else (App\Support\Bots);
 *   a visit  is a person's once the browser confirmed it — the page's script called home (`meta.js`), or the
 *            visitor did something only a person does (`meta.act`); a robot dressed as a browser does neither;
 *   staff    a browser an admin signed in from is left out for good, signed in or not.
 *
 * Where a visit came from is decided once per browser session (first page) and remembered in the PHP session:
 *   designer  — arrived through a designer's ?ref= link (the cookie `ref` lasts 30 days)
 *   google | seznam | bing | facebook | instagram | youtube — by utm_source or the referring site
 *   direct    — no referrer        other — any other site
 */
final class Track
{
    public const REF_COOKIE = 'ref';

    /** Session key: events of a request that ended in a redirect, waiting for the next page. */
    public const OUTBOX = 'track.outbox';

    /**
     * Events the browser hears about as well, to pass them on to Google Analytics and the Meta pixel when the
     * visitor allowed it (resources/js/site/measure.ts). Page views are not among them: those tools count their own.
     */
    public const SHARED = ['upload', 'generate', 'calculation', 'download', 'order_created', 'order_paid', 'register', 'designer_enabled', 'designer_file_uploaded', 'ref_visit', 'search'];

    /** What of an event's details may leave the server: numbers and codes, never a text somebody typed. */
    private const SHARED_META = ['kind', 'tool', 'value', 'currency', 'order', 'results', 'event_id'];

    public const REF_DAYS = 30;

    private const SITES = [
        'google' => ['google.'], 'seznam' => ['seznam.cz'], 'bing' => ['bing.com'],
        'facebook' => ['facebook.com', 'fb.com', 'fb.me'], 'instagram' => ['instagram.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
    ];

    /** What only a person does. One of these makes the visit a person's even when its script never called home. */
    public const ACTIONS = ['upload', 'generate', 'calculation', 'order_created', 'order_paid', 'register', 'search', 'designer_enabled', 'designer_import', 'designer_file_uploaded'];

    /** Pages whose address names public content; any other address with a parameter is kept as its pattern (/c/{calculation}): private links carry tokens. */
    private const PUBLIC_PAGES = ['printers.show', 'designers.show', 'collections.show', 'blog.show', 'models.show', 'catalog.category', 'catalog.show'];

    /** Session keys: the id of this browser session's `visit`, and how it was confirmed already. */
    private const VISIT = 'visit_id';

    private const CONFIRMED = 'visit_confirmed';

    /** @param  array<string, mixed>  $meta */
    public static function event(string $type, ?Model $subject = null, array $meta = [], ?string $subjectType = null): ?Event
    {
        try {
            $request = request();
            if (self::staff($request)) {
                return null;
            }
            $origin = self::origin($request);
            if (in_array($type, self::ACTIONS, true)) {
                self::confirm($request, 'act');
            }
            if (in_array($type, self::SHARED, true)) {
                $request->attributes->set('track.fired', array_merge((array) $request->attributes->get('track.fired', []), [
                    ['type' => $type, 'meta' => array_intersect_key($meta, array_flip(self::SHARED_META))],
                ]));
            }

            return Event::create([
                'session_id' => $request->attributes->get('anon_session')?->id,
                'user_id' => $request->user()?->id,
                'locale' => Locales::current(),
                'type' => $type,
                'subject_type' => $subjectType ?? ($subject ? self::typeOf($subject) : null),
                'subject_id' => $subject?->getKey(),
                'source' => $origin['source'],
                'ref_slug' => $origin['ref'],
                'utm' => $origin['utm'] ?: null,
                'meta' => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Event was not recorded', ['type' => $type, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The first page of a browser session: one `visit` with where it landed. The funnels of the admin start here.
     * Robots, scripts and requests without a session are not visits.
     */
    public static function visit(Request $request, ?string $tool = null): ?Event
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || Bots::is($request) || ! $request->hasSession() || $request->session()->has('visited')) {
            return null;
        }
        $request->session()->put('visited', now()->timestamp);
        $visit = self::event(Event::VISIT, null, array_filter(['path' => self::pathOf($request), 'tool' => $tool]));
        if ($visit) {
            $request->session()->put(self::VISIT, $visit->id);
        }

        return $visit;
    }

    /** A page a robot fetched: which robot and which page, no session and nothing about the machine behind it. */
    public static function crawl(Request $request, string $bot): ?Event
    {
        try {
            return Event::create(['type' => Event::CRAWL, 'source' => mb_substr($bot, 0, 20), 'locale' => Locales::current(), 'meta' => ['path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 190)]]);
        } catch (\Throwable $e) {
            Log::warning('Crawl was not recorded', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The page's script called home (resources/js/site/measure.ts → POST /api/seen): a browser really showed the
     * page. The visit of this session is confirmed as a person's, and the page counts as one page view (`page`),
     * at most once per address in a row.
     */
    public static function seen(Request $request, string $address): void
    {
        try {
            if (Bots::byAgent($request->userAgent()) !== null || ! $request->hasSession() || self::staff($request)) {
                return;
            }
            $path = self::pathOfAddress($address);
            self::confirm($request, 'js', $path);
            if ($request->session()->get('page_last') !== $path) {
                $request->session()->put('page_last', $path);
                self::event(Event::PAGE, null, ['path' => $path], 'page');
            }
        } catch (\Throwable $e) {
            Log::warning('Page view was not recorded', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Marks this session's visit as a person's: `js` = its script ran, `act` = the visitor did something.
     * A script that calls home in a session without a visit (the session began before visits were confirmed, or its
     * first page was not counted) is the visit itself.
     */
    private static function confirm(Request $request, string $by, ?string $path = null): void
    {
        if (! $request->hasSession() || in_array($by, (array) $request->session()->get(self::CONFIRMED, []), true)) {
            return;
        }
        $session = $request->session();
        $anon = $request->attributes->get('anon_session')?->id;
        $visit = $session->has(self::VISIT) ? Event::find($session->get(self::VISIT)) : null;
        $visit ??= $anon ? Event::where('session_id', $anon)->where('type', Event::VISIT)->where('created_at', '>', now()->subDay())->latest('id')->first() : null;
        if (! $visit && $by === 'js') {
            $session->put('visited', now()->timestamp);
            $visit = self::event(Event::VISIT, null, array_filter(['path' => $path]));
        }
        if (! $visit) {
            return;
        }
        $visit->forceFill(['meta' => [$by => true] + (array) $visit->meta])->save();
        $session->put(self::VISIT, $visit->id);
        $session->push(self::CONFIRMED, $by);
    }

    /**
     * True for a browser of the staff. The first time an admin is seen in a browser, the browser is remembered
     * (anonymous_sessions.staff) and what it did in the last 30 days is marked `meta.staff`, so it leaves the numbers.
     */
    public static function staff(Request $request): bool
    {
        $anon = $request->attributes->get('anon_session');
        if ($anon?->staff) {
            return true;
        }
        if (! $request->user()?->isAdmin()) {
            return false;
        }
        if ($anon) {
            $anon->forceFill(['staff' => true])->saveQuietly();
            foreach (Event::where('session_id', $anon->id)->where('created_at', '>', now()->subDays(30))->get() as $event) {
                $event->forceFill(['meta' => ['staff' => true] + (array) $event->meta])->save();
            }
        }

        return true;
    }

    /** The address of the page being shown, as the statistics keep it. */
    public static function pathOf(Request $request): string
    {
        return self::masked($request->route(), '/'.ltrim($request->path(), '/'));
    }

    /** The same for an address the browser reported. */
    public static function pathOfAddress(string $address): string
    {
        $path = '/'.trim((string) parse_url($address, PHP_URL_PATH), '/');
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable) {
            return '/?';   // no page of ours
        }

        return self::masked($route, $path);
    }

    /**
     * One page = one line in every language (the language is the event's `locale`), and no private token: an address
     * with parameters is kept as written only for public content, otherwise as its pattern.
     */
    private static function masked(?MatchedRoute $route, string $path): string
    {
        $first = explode('/', ltrim($path, '/'))[0];
        if (in_array($first, Locales::PREFIXED, true)) {
            $path = '/'.ltrim(substr(ltrim($path, '/'), strlen($first)), '/');
        }
        if ($route && array_diff($route->parameterNames(), ['locale']) && ! in_array(Locales::baseName($route->getName()), self::PUBLIC_PAGES, true)) {
            $path = '/'.ltrim((string) preg_replace('#^\{locale\}/?#', '', $route->uri()), '/');
        }

        return mb_substr($path, 0, 190);
    }

    /**
     * Somebody opened a tool's page: at most once per visitor, tool and half hour (like a page view of a model).
     */
    public static function tool(string $tool): ?Event
    {
        $request = request();
        if (Bots::is($request)) {
            return null;
        }
        $session = $request->attributes->get('anon_session')?->id;
        if ($session && Event::where('session_id', $session)->where('type', Event::VIEW)->where('subject_type', 'tool')->where('meta->tool', $tool)->where('created_at', '>', now()->subMinutes(30))->exists()) {
            return null;
        }

        return self::event(Event::VIEW, null, ['tool' => $tool], 'tool');
    }

    /**
     * Shared events recorded during this request so far.
     *
     * @return list<array{type: string, meta: array<string, mixed>}>
     */
    public static function fired(?Request $request = null): array
    {
        return array_values((array) ($request ?? request())->attributes->get('track.fired', []));
    }

    /**
     * What the page being rendered tells its scripts: the events of this request and those a redirect left behind.
     * Handing them over empties both, so nothing is reported twice.
     *
     * @return list<array{type: string, meta: array<string, mixed>}>
     */
    public static function forBrowser(): array
    {
        try {
            $request = request();
            $waiting = $request->hasSession() ? (array) $request->session()->pull(self::OUTBOX, []) : [];
            $now = self::fired($request);
            $request->attributes->set('track.fired', []);

            return array_values(array_merge($waiting, $now));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * A page view, at most once per visitor, subject and half hour (reloads and back-button visits do not count twice).
     */
    public static function view(Model $subject): ?Event
    {
        if (Bots::is(request())) {
            return null;
        }
        $session = request()->attributes->get('anon_session')?->id;
        if ($session && Event::where('session_id', $session)->where('type', Event::VIEW)->where('subject_type', self::typeOf($subject))
            ->where('subject_id', $subject->getKey())->where('created_at', '>', now()->subMinutes(30))->exists()) {
            return null;
        }

        return self::event(Event::VIEW, $subject);
    }

    public static function typeOf(Model $subject): string
    {
        return match (true) {
            $subject instanceof DesignerProfile => 'designer',
            $subject instanceof DesignerModel => 'designer_model',
            default => Str::snake(class_basename($subject)),
        };
    }

    /** The designer whose link brought this visitor (this request's ?ref=, else the cookie). */
    public static function ref(?Request $request = null): ?string
    {
        $request ??= request();
        $ref = $request->attributes->get('ref_slug') ?? $request->cookie(self::REF_COOKIE);

        return is_string($ref) && preg_match('/^[a-z0-9-]{1,140}$/', $ref) ? $ref : null;
    }

    /** @return array{source: string, ref: ?string, utm: array<string, string>} */
    public static function origin(Request $request): array
    {
        $ref = self::ref($request);
        $stored = $request->hasSession() ? $request->session()->get('origin') : null;
        if (! is_array($stored)) {
            $utm = array_filter(array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 80) : null, $request->only(['utm_source', 'utm_medium', 'utm_campaign'])));
            $stored = ['source' => self::classify((string) ($utm['utm_source'] ?? ''), (string) $request->headers->get('referer'), $request->getHost()), 'utm' => $utm];
            if ($request->hasSession()) {
                $request->session()->put('origin', $stored);
            }
        }

        return ['source' => $ref ? 'designer' : $stored['source'], 'ref' => $ref, 'utm' => (array) ($stored['utm'] ?? [])];
    }

    public static function classify(string $utmSource, string $referer, string $ownHost): string
    {
        $utmSource = strtolower($utmSource);
        foreach (array_keys(self::SITES) as $name) {
            if ($utmSource !== '' && str_contains($utmSource, $name)) {
                return $name;
            }
        }
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '' || $host === strtolower($ownHost)) {
            return $utmSource !== '' ? 'other' : 'direct';
        }
        foreach (self::SITES as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($host, $needle)) {
                    return $name;
                }
            }
        }

        return 'other';
    }
}
