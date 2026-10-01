<?php

namespace App\Support;

/**
 * What the visitor allowed in the cookie bar. The cookie `consent` is written by the browser (the bar is a script)
 * and reads "a1m0": analytics yes, marketing no. No cookie = nothing decided = nothing but the necessary is loaded.
 *
 * Our own statistics (the `events` table) need no consent: first-party, no extra cookie, no data leaves the site.
 */
final class Consent
{
    public const COOKIE = 'consent';

    public const DAYS = 180;

    /** @return array{analytics: bool, marketing: bool}|null null = the visitor has not chosen yet */
    public static function given(): ?array
    {
        $value = app()->bound('request') ? request()->cookie(self::COOKIE) : null;
        if (! is_string($value) || ! preg_match('/^a([01])m([01])$/', $value, $m)) {
            return null;
        }

        return ['analytics' => $m[1] === '1', 'marketing' => $m[2] === '1'];
    }

    public static function allows(string $purpose): bool
    {
        return (bool) (self::given()[$purpose] ?? false);
    }

    /** Everything the measuring script of a page needs. */
    public static function forScripts(): array
    {
        return [
            'ga' => config('services.ga4.id') ?: null,
            'pixel' => config('services.meta.pixel_id') ?: null,
            'consent' => self::given(),
            'days' => self::DAYS,
            'events' => Track::forBrowser(),
        ];
    }
}
