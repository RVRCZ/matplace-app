<?php

namespace App\Support;

use App\Exceptions\UntranslatedPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Languages live in the URL: Czech without a prefix, the others as /en/… and /es/….
 * Every page route is registered twice (routes/web.php): once plain, once under "{locale}" with the name prefix "l.".
 * LocalizedUrlGenerator picks the right twin, so route('tools') gives /en/tools on an English page.
 */
final class Locales
{
    public const DEFAULT = 'cs';

    public const SUPPORTED = ['cs', 'en', 'es'];

    /** Languages that carry a prefix in the URL. */
    public const PREFIXED = ['en', 'es'];

    public const NAME_PREFIX = 'l.';

    /** Set on the first page a visitor gets, so the start page sends nobody to another language twice. */
    public const SEEN_COOKIE = 'lang_seen';

    private const PAGE_KEY = 'page_locales';

    public static function pattern(): string
    {
        return implode('|', self::PREFIXED);
    }

    public static function supported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED, true);
    }

    public static function current(): string
    {
        $locale = app()->getLocale();

        return self::supported($locale) ? $locale : self::DEFAULT;
    }

    /** "/en" for English, "" for Czech: for the few places that build a path by hand. */
    public static function prefix(?string $locale = null): string
    {
        $locale ??= self::current();

        return in_array($locale, self::PREFIXED, true) ? '/'.$locale : '';
    }

    /** Route name without the twin's prefix. */
    public static function baseName(?string $name): string
    {
        $name = (string) $name;

        return str_starts_with($name, self::NAME_PREFIX) ? substr($name, strlen(self::NAME_PREFIX)) : $name;
    }

    /** True for a route that exists in every language (has a prefixed twin). */
    public static function localized(?string $name): bool
    {
        $base = self::baseName($name);

        return $base !== '' && Route::has(self::NAME_PREFIX.$base);
    }

    /** request()->routeIs() that does not care which language twin answered. */
    public static function routeIs(string ...$patterns): bool
    {
        $name = self::baseName(Route::currentRouteName());

        return $name !== '' && Str::is($patterns, $name);
    }

    /** Language of a JSON call: the page that made it says so in a header, or its address does. */
    public static function forApi(Request $request): string
    {
        $header = $request->headers->get('X-Locale');
        if (self::supported($header)) {
            return $header;
        }
        $referer = (string) $request->headers->get('referer');
        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return self::fromPath((string) parse_url($referer, PHP_URL_PATH));
        }

        return self::DEFAULT;
    }

    /** Language named by the first segment of a path. */
    public static function fromPath(string $path): string
    {
        $first = explode('/', trim($path, '/'))[0] ?? '';

        return in_array($first, self::PREFIXED, true) ? $first : self::DEFAULT;
    }

    /**
     * Language a new visitor most likely reads, from the Accept-Language list (null = leave them on the Czech page).
     * Czech and Slovak readers stay; anyone else who sent a list and has neither gets English.
     */
    public static function preferred(Request $request): ?string
    {
        $listed = false;
        foreach ($request->getLanguages() as $language) {
            $listed = true;
            $code = strtolower(substr($language, 0, 2));
            if ($code === 'cs' || $code === 'sk') {
                return null;
            }
            if (in_array($code, self::PREFIXED, true)) {
                return $code;
            }
        }

        return $listed ? 'en' : null;
    }

    public static function isBot(Request $request): bool
    {
        return Bots::byAgent($request->userAgent()) !== null;
    }

    // ── What the current page offers ─────────────────────────────────────────

    /**
     * The page exists only in these languages (an article without a translation, a model described only in Czech).
     * Asked for in another one it answers 404 with a link to the Czech version, and it gets no hreflang for it.
     *
     * @param  list<string>  $locales
     */
    public static function only(array $locales): void
    {
        $locales = array_values(array_intersect(self::SUPPORTED, array_merge([self::DEFAULT], $locales)));
        request()->attributes->set(self::PAGE_KEY, $locales);
        if (! in_array(self::current(), $locales, true)) {
            throw new UntranslatedPage(self::urlFor(self::DEFAULT) ?? url('/'));
        }
    }

    /** @return list<string> */
    public static function available(): array
    {
        return request()->attributes->get(self::PAGE_KEY, self::SUPPORTED);
    }

    /** Address of the current page in another language (null when the page has no language twins). */
    public static function urlFor(string $locale, bool $withQuery = false): ?string
    {
        $route = request()->route();
        if (! $route || ! self::localized($route->getName())) {
            return null;
        }
        $parameters = array_intersect_key($route->originalParameters(), array_flip($route->parameterNames()));
        unset($parameters['locale']);
        if ($withQuery) {
            $parameters += array_diff_key(request()->query(), ['lang' => 1, 'locale' => 1]);
        }

        return route(self::baseName($route->getName()), $parameters + ['locale' => $locale]);
    }

    /**
     * hreflang set of the current page: every language it exists in, empty when there is just one or the page is not indexed.
     *
     * @return array<string, string>
     */
    public static function alternates(): array
    {
        $available = self::available();
        if (count($available) < 2 || self::noindex()) {
            return [];
        }
        $out = [];
        foreach ($available as $locale) {
            if ($url = self::urlFor($locale)) {
                $out[$locale] = $url;
            }
        }

        return count($out) > 1 ? $out : [];
    }

    /** The one address search engines should keep for the current page: no tracking parameters, the page number stays. */
    public static function canonical(): ?string
    {
        $url = self::urlFor(self::current());
        if ($url === null) {
            return null;
        }
        $page = (int) request()->query('page', 1);

        return $page > 1 ? $url.'?page='.$page : $url;
    }

    /** Private and one-off pages (config/seo.php) stay out of search engines. */
    public static function noindex(): bool
    {
        $name = self::baseName(Route::currentRouteName());

        return $name === '' || Str::is(config('seo.noindex', []), $name);
    }

    /**
     * The language switch of the header: every language with the address of this very page, query included.
     * A language the page does not exist in leads to that language's start page.
     *
     * @return array<string, string>
     */
    public static function switcher(): array
    {
        $route = request()->route();
        if (! $route || ! self::localized($route->getName())) {
            return [];
        }
        $available = self::available();
        $out = [];
        foreach (self::SUPPORTED as $locale) {
            $out[$locale] = in_array($locale, $available, true)
                ? (string) self::urlFor($locale, request()->isMethod('GET'))
                : route('home', ['locale' => $locale]);
        }

        return $out;
    }
}
