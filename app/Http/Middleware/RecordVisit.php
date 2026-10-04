<?php

namespace App\Http\Middleware;

use App\Support\Bots;
use App\Support\Locales;
use App\Support\Track;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Our own statistics of pages: the first page of a browser session is one `visit` (with the page it landed on),
 * and a tool's page is one view of that tool. Only pages that were really shown count: an HTML answer with 200.
 * The same page fetched by a robot is one `crawl`; the admin's own pages are nobody's visit.
 */
class RecordVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $page = ($request->isMethod('GET') || $request->isMethod('HEAD')) && ! $request->expectsJson() && ! $request->is('admin', 'admin/*')
            && $response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html');
        if ($page && ($bot = Bots::name($request)) !== null) {
            Track::crawl($request, $bot);
        } elseif ($page) {
            $tool = self::toolOf($request);
            Track::visit($request, $tool);
            if ($tool !== null) {
                Track::tool($tool);
            }
        }

        return $response;
    }

    /** The tool whose page this is (config/tools.php), null for any other page. */
    private static function toolOf(Request $request): ?string
    {
        $name = Locales::baseName((string) $request->route()?->getName());
        if ($name === '') {
            return null;
        }
        foreach ((array) config('tools') as $key => $tool) {
            if (($tool['route'] ?? null) === $name && ! empty($tool['available'])) {
                return (string) $key;
            }
        }

        return null;
    }
}
