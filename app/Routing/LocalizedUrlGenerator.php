<?php

namespace App\Routing;

use App\Support\Locales;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;

/**
 * route() that knows about languages. A page route has a twin under "{locale}" (name prefix "l."); this picks
 * the twin for the language being rendered, or for the one named in the parameters: route('tools', ['locale' => 'es']).
 * Routes without a twin (API, admin, files, webhooks) pass through untouched.
 */
class LocalizedUrlGenerator extends UrlGenerator
{
    public function toRoute($route, $parameters, $absolute)
    {
        $base = Locales::baseName($route->getName());
        $twin = $base !== '' ? $this->routes->getByName(Locales::NAME_PREFIX.$base) : null;
        $plain = $twin ? $this->routes->getByName($base) : null;
        if (! $twin || ! $plain) {
            return parent::toRoute($route, $parameters, $absolute);
        }

        $parameters = Arr::wrap($parameters);
        $locale = $parameters['locale'] ?? null;
        unset($parameters['locale']);
        if (! Locales::supported($locale)) {
            $locale = Locales::current();
        }

        return in_array($locale, Locales::PREFIXED, true)
            ? parent::toRoute($twin, ['locale' => $locale] + $parameters, $absolute)
            : parent::toRoute($plain, $parameters, $absolute);
    }
}
