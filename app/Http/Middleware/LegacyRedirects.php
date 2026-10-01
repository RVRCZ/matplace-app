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
            $target = self::targetFor('/'.ltrim($request->path(), '/'), (string) $request->getQueryString());
            if ($target !== null) {
                return redirect()->to($target[0], $target[1]);
            }
        }

        return $next($request);
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
