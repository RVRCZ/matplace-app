<?php

namespace App\Engines\Translate;

final class Translation
{
    /**
     * @param  string  $from  cs | en | es | other (a language the site does not have)
     * @param  array<string, string>  $texts  language → text; never contains `$from`
     */
    public function __construct(public readonly string $from, public readonly array $texts) {}
}
