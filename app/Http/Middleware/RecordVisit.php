<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use App\Support\Track;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Our own statistics of pages: the first page of a browser session is one `visit` (with the page it landed on),
 * and a tool's page is one view of that tool. Only pages that were really shown count: an HTML answer with 200.
 */
class RecordVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('GET') && ! $request->expectsJson() && $response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
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
