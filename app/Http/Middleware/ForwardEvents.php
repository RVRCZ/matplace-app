<?php

namespace App\Http\Middleware;

use App\Support\Track;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands the events recorded during a request (App\Support\Track) to the browser, which passes them on to Google
 * Analytics and the Meta pixel when the visitor allowed that. The server itself sends nothing to anybody.
 *
 *   a page             the layout prints them (window.MP_MEASURE.events)
 *   a redirect         kept in the session for the page the browser lands on
 *   an answer to fetch the header X-Matplace-Events (resources/js/site/measure.ts reads it)
 */
class ForwardEvents
{
    public const HEADER = 'X-Matplace-Events';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $fired = Track::fired($request);
        if (! $fired) {
            return $response;
        }
        if ($response instanceof RedirectResponse) {
            if ($request->hasSession()) {
                $request->session()->put(Track::OUTBOX, array_slice(array_merge((array) $request->session()->get(Track::OUTBOX, []), $fired), -20));
            }
        } elseif (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set(self::HEADER, (string) json_encode($fired, JSON_UNESCAPED_SLASHES));
        }

        return $response;
    }
}
