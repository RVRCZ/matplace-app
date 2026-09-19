<?php

namespace App\Http\Middleware;

use App\Models\AnonymousSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every visitor gets a long-lived anonymous session (cookie mp_sid) so uploads and calculations
 * have an owner before any account exists. Registration later claims the session.
 */
class EnsureAnonymousSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->cookie(AnonymousSession::COOKIE, '');
        $session = $token !== '' ? AnonymousSession::where('token', $token)->first() : null;
        $created = false;

        if (! $session) {
            $session = AnonymousSession::start($request->ip(), $request->userAgent());
            $created = true;
        } elseif (! $session->last_seen_at || $session->last_seen_at->lt(now()->subMinutes(10))) {
            $session->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('anon_session', $session);
        $response = $next($request);

        if ($created || $request->cookie(AnonymousSession::COOKIE) !== $session->token) {
            $response->headers->setCookie(new Cookie(
                AnonymousSession::COOKIE,
                $session->token,
                now()->addMinutes(AnonymousSession::COOKIE_MINUTES),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'Lax',
            ));
        }

        return $response;
    }
}
