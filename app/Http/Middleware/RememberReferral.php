<?php

namespace App\Http\Middleware;

use App\Models\DesignerProfile;
use App\Models\Event;
use App\Support\Locales;
use App\Support\Track;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A designer's link carries ?ref=<their slug>. The visitor who arrives through it is remembered for 30 days
 * (cookie `ref`), so a print ordered next week still counts as brought by that designer, and the arrival itself
 * is one `ref_visit` in the statistics.
 */
class RememberReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');
        $known = null;
        if ($request->isMethod('GET') && is_string($ref) && preg_match('/^[a-z0-9-]{1,140}$/', $ref) && ! Locales::isBot($request)) {
            $known = DesignerProfile::where('slug', $ref)->first();
            if ($known) {
                $request->attributes->set('ref_slug', $known->slug);
            }
        }
        $response = $next($request);

        if ($known) {
            // one arrival, however many pages of the visit still carry the parameter
            $session = $request->attributes->get('anon_session')?->id;
            $counted = $session && Event::where('session_id', $session)->where('type', Event::REF_VISIT)->where('ref_slug', $known->slug)->where('created_at', '>', now()->subMinutes(30))->exists();
            if (! $counted) {
                Track::event(Event::REF_VISIT, $known);
            }
            $response->headers->setCookie(cookie(Track::REF_COOKIE, $known->slug, Track::REF_DAYS * 24 * 60, '/', null, $request->isSecure(), true, false, 'Lax'));
        }

        return $response;
    }
}
