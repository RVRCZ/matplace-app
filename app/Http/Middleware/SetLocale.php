<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of a page is the one in its address and nothing else: /tools is Czech, /en/tools English.
 * JSON calls (/api/*) answer in the language of the page that made them. Everything else without twins is Czech.
 *
 * Two redirects live here:
 *  - ?lang=xx (the old switch, still in links around the web) → 301 to the address of that language;
 *  - the very first visit of the plain start page by somebody who does not read Czech → 302 to /en or /es.
 *    Never a robot, never another page, never twice (cookie lang_seen).
 */
class SetLocale
{
    /** @deprecated use Locales::SUPPORTED */
    public const SUPPORTED = Locales::SUPPORTED;

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = (string) $route?->getName();
        $localized = Locales::localized($name);
        $locale = Locales::DEFAULT;

        if ($route && str_starts_with($route->uri(), '{locale}')) {
            $locale = (string) $route->parameter('locale');
            // controllers take their parameters by position: the language must not be one of them
            $route->forgetParameter('locale');
        } elseif (! $localized && $request->is('api/*')) {
            $locale = Locales::forApi($request);
        } elseif (! $localized && $request->hasSession() && Locales::supported($request->session()->get('locale_once'))) {
            // a round trip through another site (OAuth) comes back to an address without a language
            $locale = $request->session()->pull('locale_once');
        }
        App::setLocale(Locales::supported($locale) ? $locale : Locales::DEFAULT);

        $page = $localized && $request->isMethod('GET') && ! $request->expectsJson();
        if ($page && $request->query->has('lang')) {
            return $this->seen($request, redirect()->to($this->address($request, $request->query('lang')), 301));
        }
        if ($page && $this->firstVisitElsewhere($request, $name) && ($to = $this->wanted($request))) {
            $response = redirect()->to($this->address($request, $to), 302);
            $response->headers->set('Vary', 'Accept-Language, Cookie');

            return $this->seen($request, $response);
        }

        $response = $next($request);

        return $page ? $this->seen($request, $response) : $response;
    }

    /** Same page, same parameters, same query (without lang) in another language. */
    private function address(Request $request, mixed $locale): string
    {
        $route = $request->route();
        $parameters = array_intersect_key($route->parameters(), array_flip($route->parameterNames()));
        $query = array_diff_key($request->query(), ['lang' => 1, 'locale' => 1]);

        return route(Locales::baseName($route->getName()), $parameters + $query + [
            'locale' => Locales::supported($locale) ? $locale : Locales::current(),
        ]);
    }

    private function firstVisitElsewhere(Request $request, string $name): bool
    {
        return $name === 'home' && ! $request->cookies->has(Locales::SEEN_COOKIE) && ! Locales::isBot($request);
    }

    /** The language chosen on the old site (cookie lang), else what the browser asks for. */
    private function wanted(Request $request): ?string
    {
        $cookie = $request->cookie('lang');
        if (Locales::supported($cookie)) {
            return in_array($cookie, Locales::PREFIXED, true) ? $cookie : null;
        }

        return Locales::preferred($request);
    }

    private function seen(Request $request, Response $response): Response
    {
        if (! $request->cookies->has(Locales::SEEN_COOKIE)) {
            $response->headers->setCookie(new Cookie(Locales::SEEN_COOKIE, '1', now()->addYear(), '/', null, $request->isSecure(), true, false, 'Lax'));
        }

        return $response;
    }
}
