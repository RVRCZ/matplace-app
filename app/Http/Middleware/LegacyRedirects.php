<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The old site's addresses on the new application (config/legacy.php). Runs before routing, so it costs one array
 * lookup for the pages of the new site and never reaches a controller for the old ones.
 *
 *   an old page that has a new one      → 301 to it
 *   /stahnout/{slug}, /koupit-model/…   → 301 to /model/{slug}
 *   accounts, orders, printers, files   → 302 to the same address on the old site (it will go away one day)
 *
 * /model/{slug} and /blog/{slug} are not redirected at all: they are served here with the same content.
 */
class LegacyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            if (self::gone('/'.ltrim($request->path(), '/')) && ! self::routed($request)) {
                // a file (a thumbnail of the old catalogue) answers in two words; a page gets the "no longer exists" page
                if (preg_match('~\.[a-z0-9]{2,5}$~i', $request->path())) {
                    return response("Gone\n", 410, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
                }
                abort(410);
            }
            $target = self::targetFor('/'.ltrim($request->path(), '/'), (string) $request->getQueryString());
            if ($target !== null) {
                return redirect()->to($target[0], $target[1]);
            }
        }

        return $next($request);
    }

    /**
     * An address of the old site (or of the e-shop before it) that has no successor: answered 410 "gone", so search
     * engines stop asking for it (config/legacy.php `gone`, docs/AP.md §14). Never an address a route of ours answers.
     */
    public static function gone(string $path): bool
    {
        $path = '/'.ltrim($path, '/');
        foreach ((array) config('legacy.gone', []) as $pattern) {
            if (@preg_match((string) $pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Does a route of the application answer this request? Asked only for an address a `gone` pattern matched. */
    private static function routed(Request $request): bool
    {
        try {
            Route::getRoutes()->match($request);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: int}|null where the address leads and how (301 | 302); null = not an old address
     */
    public static function targetFor(string $path, string $query = ''): ?array
    {
        $path = '/'.trim($path, '/');
        $first = explode('/', ltrim($path, '/'))[0];
        if ($first === '') {
            return null;
        }
        $page = config('legacy.pages')[$path] ?? null;
        if ($page !== null) {
            if (str_starts_with($page, 'route:')) {
                $name = substr($page, 6);
                if (! Route::has($name)) {
                    return [url('/'), 302];   // the page is not built (yet): the home page, and not for good
                }
                $page = localized_route($name);
            }

            return [str_starts_with($page, 'http') ? $page : url($page), 301];
        }
        if (in_array($first, (array) config('legacy.to_model'), true)) {
            $slug = explode('/', ltrim($path, '/'))[1] ?? '';

            return [preg_match('/^[a-z0-9-]+$/', $slug) ? url('/model/'.$slug) : url('/model'), 301];
        }
        if (in_array($first, (array) config('legacy.to_legacy'), true)) {
            return [config('legacy.host').$path.($query !== '' ? '?'.$query : ''), 302];
        }

        return null;
    }
}
