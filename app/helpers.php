<?php

use App\Support\Locales;
use Illuminate\Support\Arr;

if (! function_exists('localized_route')) {
    /**
     * Address of a page in a given language (the current one when none is named).
     * route() already follows the language being rendered; this is for links that cross languages.
     */
    function localized_route(string $name, mixed $parameters = [], ?string $locale = null, bool $absolute = true): string
    {
        $parameters = Arr::wrap($parameters);
        $parameters['locale'] = $locale ?? Locales::current();

        return route(Locales::baseName($name), $parameters, $absolute);
    }
}
