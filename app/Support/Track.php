<?php

namespace App\Support;

use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Our own statistics, written to `events`. First-party and without any extra cookie: a visitor is the anonymous
 * session every visitor already has. Page views of robots are left out. Recording never breaks the page that calls it.
 *
 * Where a visit came from is decided once per browser session (first page) and remembered in the PHP session:
 *   designer  — arrived through a designer's ?ref= link (the cookie `ref` lasts 30 days)
 *   google | seznam | bing | facebook | instagram — by utm_source or the referring site
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
    ];

    /** @param  array<string, mixed>  $meta */
    public static function event(string $type, ?Model $subject = null, array $meta = [], ?string $subjectType = null): ?Event
    {
        try {
            $request = request();
            $origin = self::origin($request);
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
        if (! $request->isMethod('GET') || $request->expectsJson() || Locales::isBot($request) || ! $request->hasSession() || $request->session()->has('visited')) {
            return null;
        }
        $request->session()->put('visited', now()->timestamp);

        return self::event(Event::VISIT, null, array_filter(['path' => '/'.ltrim($request->path(), '/'), 'tool' => $tool]));
    }

    /**
     * Somebody opened a tool's page: at most once per visitor, tool and half hour (like a page view of a model).
     */
    public static function tool(string $tool): ?Event
    {
        $request = request();
        if (Locales::isBot($request)) {
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
        if (Locales::isBot(request())) {
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
