<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/** Locale from ?lang=, then cookie, then Accept-Language; default cs. Supported: cs, en. */
class SetLocale
{
    public const SUPPORTED = ['cs', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;
        $query = $request->query('lang');
        if (is_string($query) && in_array($query, self::SUPPORTED, true)) {
            $locale = $query;
            Cookie::queue('lang', $locale, 60 * 24 * 365);
        } elseif (in_array($request->cookie('lang'), self::SUPPORTED, true)) {
            $locale = $request->cookie('lang');
        } else {
            $pref = $request->getPreferredLanguage(self::SUPPORTED);
            $locale = in_array($pref, self::SUPPORTED, true) ? $pref : config('app.locale');
        }
        App::setLocale($locale);

        return $next($request);
    }
}
