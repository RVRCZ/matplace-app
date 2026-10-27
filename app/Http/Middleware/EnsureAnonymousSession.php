<?php

namespace App\Http\Middleware;

use App\Models\AnonymousSession;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        if (! $session && $request->isMethod('GET') && Locales::isBot($request)) {

            // a robot fetching a page never sends the cookie back: no row for it (27 000 a day in Oct 2026, audit B9); an

            // unsaved session stands in so the rest of the request reads it as usual (no id, no staff flag)

            // (with a token of its own: should a robot still do something that claims it, the save makes an ordinary row)
            $request->attributes->set('anon_session', new AnonymousSession(['token' => Str::random(48), 'ip' => $request->ip() ? substr($request->ip(), 0, 45) : null, 'user_agent' => substr((string) $request->userAgent(), 0, 255), 'last_seen_at' => now()]));

            return $next($request);

        }

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
