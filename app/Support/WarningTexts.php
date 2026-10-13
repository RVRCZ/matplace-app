<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/**
 * The warnings a parametric tool may show under its preview, as the page hands them to its script. The engine answers
 * with a code ("thin_flutes"), the script looks its text up as "param.warn.<code>": a text that is written but was
 * never put on the page's list came out as the bare key (the vase said "param.warn.thin_flutes"). So the list is not
 * kept by hand: every warning that has a text is on it.
 */
final class WarningTexts
{
    /** @var array<string, list<string>> */
    private static array $codes = [];

    /**
     * 'param.warn.<code>' => text for every warning, and for the tools of the picture in colours ('shape') their own
     * 'shape.warn.<code>' too, a tool's wording first ("param.shape.warn.magnet_no_room.badge").
     *
     * @return array<string, string>
     */
    public static function forPage(string $kind, ?string $family): array
    {
        $texts = [];
        foreach (self::codes('param.warn.') as $code) {
            $texts['param.warn.'.$code] = NextStep::text('param.warn.'.$code);
        }
        if ($family === 'shape') {
            foreach (self::codes('param.shape.warn.') as $code) {
                $key = 'param.shape.warn.'.$code;
                $texts['shape.warn.'.$code] = NextStep::text(Lang::has($key.'.'.$kind) ? $key.'.'.$kind : $key);
            }
        }

        // a code that has only wordings for other tools or other ways of ordering has no text here: the script then
        // falls back on the general one instead of showing a key
        return array_filter($texts, fn (string $text, string $as) => $text !== 'param.'.$as && $text !== $as, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The codes that have a text under this prefix, in the texts kept as one list (lang/<locale>.json) and in
     * lang/<locale>/param.php. "magnet_no_room.badge" and "portrait_fine.farm" are wordings of the code before the dot.
     *
     * @return list<string>
     */
    public static function codes(string $prefix): array
    {
        $locale = app()->getLocale();

        return self::$codes[$locale.' '.$prefix] ??= (function () use ($prefix, $locale): array {
            $keys = [];
            foreach (array_unique([$locale, (string) config('app.fallback_locale')]) as $from) {
                $keys = [...$keys, ...array_keys(app('translator')->getLoader()->load($from, '*', '*'))];
                $keys = [...$keys, ...array_map(fn ($k) => 'param.'.$k, array_keys(Arr::dot((array) trans('param', [], $from))))];
            }
            $codes = [];
            foreach ($keys as $key) {
                if (str_starts_with((string) $key, $prefix)) {
                    $codes[explode('.', substr((string) $key, strlen($prefix)))[0]] = true;
                }
            }

            return array_map('strval', array_keys($codes));
        })();
    }
}
